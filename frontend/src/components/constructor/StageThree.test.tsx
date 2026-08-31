import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { ConstructorProductSize, GiftSizeProfile } from "../../interfaces/giftConstructor";

const mocks = vi.hoisted(() => ({
  quoteSimple: vi.fn(),
  createSimpleGift: vi.fn(),
  addGift: vi.fn(),
  onSeal: vi.fn(),
  online: true,
}));

vi.mock("../../api/giftConstructorAPI", () => ({
  giftConstructorApi: {
    quoteSimple: mocks.quoteSimple,
    createSimpleGift: mocks.createSimpleGift,
  },
}));
vi.mock("../../hooks/useAuth", () => ({ useAuth: () => ({ user: { id: 7 } }) }));
vi.mock("../../hooks/useCustomerCart", () => ({ useCustomerCart: () => ({ addGift: mocks.addGift }) }));
vi.mock("../../hooks/useOnlineStatus", () => ({ useOnlineStatus: () => mocks.online }));

import StageThree from "./StageThree";

const box: GiftSizeProfile = {
  id: 4,
  code: "box-simple-3x1",
  name: "Коробка 3 × 1",
  kind: "box",
  width_cells: 3,
  height_cells: 1,
  can_rotate: false,
  max_weight_grams: null,
  default_markup_amount: 50,
  simple_constructor_enabled: true,
  simple_requirements: {
    tea_count: 2,
    sweet_count: 1,
    total_items: 3,
    allow_duplicate_products: true,
  },
};

function productSize(id: number, name: string, role: "tea" | "sweet"): ConstructorProductSize {
  return {
    id,
    label: "100 г",
    constructor_role: role,
    product_quantity: 100,
    product: {
      id: id + 100,
      name,
      price: 250,
      stock_unit: "gram",
      price_unit_quantity: 100,
      image: null,
    },
    size: box,
  };
}

const teas = [productSize(11, "Ассам", "tea"), productSize(12, "Сенча", "tea")];
const sweets = [productSize(21, "Пастила", "sweet")];

function renderStage(sealed = false, sealing = false) {
  return render(
    <MemoryRouter>
      <StageThree
        giftType="simplified"
        box={box}
        teas={teas}
        sweets={sweets}
        onSeal={mocks.onSeal}
        sealing={sealing}
        sealed={sealed}
        onReset={vi.fn()}
      />
    </MemoryRouter>,
  );
}

beforeEach(() => {
  Object.values(mocks).forEach((value) => {
    if (typeof value === "function" && "mockReset" in value) value.mockReset();
  });
  mocks.online = true;
  mocks.quoteSimple.mockResolvedValue({
    valid: true,
    totals: { final_total: 720 },
  });
  mocks.createSimpleGift.mockResolvedValue({ id: 40, version: 3 });
  mocks.addGift.mockResolvedValue({ id: 9, items: [], gifts: [{ id: 31 }] });
  vi.stubGlobal("crypto", { randomUUID: () => "11111111-1111-4111-8111-111111111111" });
});

describe("StageThree backend flow", () => {
  it("получает серверную quote до запуска анимации", async () => {
    renderStage();
    await screen.findByText("Цена подтверждена backend");
    expect(screen.getByText(/720\s*₽/)).toBeInTheDocument();
    expect(mocks.quoteSimple).toHaveBeenCalledWith({
      box_profile_id: 4,
      tea_product_size_ids: [11, 12],
      sweet_product_size_ids: [21],
      quantity: 1,
    });

    fireEvent.click(screen.getByRole("button", { name: /Упаковать и добавить/ }));
    expect(mocks.onSeal).toHaveBeenCalledOnce();
    expect(mocks.createSimpleGift).not.toHaveBeenCalled();
  });

  it("после полёта создаёт Gift и добавляет именно его версию в корзину", async () => {
    renderStage(true);

    await waitFor(() => expect(mocks.addGift).toHaveBeenCalledOnce());
    expect(mocks.createSimpleGift).toHaveBeenCalledWith({
      box_profile_id: 4,
      name: "Чайный подарок «Коробка 3 × 1»",
      tea_product_size_ids: [11, 12],
      sweet_product_size_ids: [21],
    });
    expect(mocks.addGift).toHaveBeenCalledWith({
      gift_id: 40,
      gift_version: 3,
      quantity: 1,
      client_instance_id: "11111111-1111-4111-8111-111111111111",
    });
    expect(await screen.findByText("✓ Подарок добавлен в корзину!")).toBeInTheDocument();
  });

  it("не отправляет команды offline", async () => {
    mocks.online = false;
    renderStage(true);

    expect(await screen.findByRole("alert")).toHaveTextContent("без подключения к сети");
    expect(mocks.quoteSimple).not.toHaveBeenCalled();
    expect(mocks.createSimpleGift).not.toHaveBeenCalled();
    expect(mocks.addGift).not.toHaveBeenCalled();
  });

  it("повторяет только идемпотентное добавление уже созданного Gift", async () => {
    mocks.addGift
      .mockRejectedValueOnce({ response: { data: { message: "Временная ошибка корзины" } } })
      .mockResolvedValueOnce({ id: 9, items: [], gifts: [{ id: 31 }] });
    renderStage(true);

    const retry = await screen.findByRole("button", { name: "Повторить добавление" });
    fireEvent.click(retry);

    await waitFor(() => expect(mocks.addGift).toHaveBeenCalledTimes(2));
    expect(mocks.createSimpleGift).toHaveBeenCalledOnce();
    expect(mocks.addGift.mock.calls[0][0].client_instance_id)
      .toBe(mocks.addGift.mock.calls[1][0].client_instance_id);
  });

  it("не повторяет неоднозначно завершившееся создание Gift", async () => {
    mocks.createSimpleGift.mockRejectedValueOnce(new Error("Network Error"));
    renderStage(true);

    expect(await screen.findByRole("alert")).toHaveTextContent("дубликат");
    expect(screen.queryByRole("button", { name: "Повторить добавление" })).not.toBeInTheDocument();
    expect(mocks.addGift).not.toHaveBeenCalled();
  });
});
