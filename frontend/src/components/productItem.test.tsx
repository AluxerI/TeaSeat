import { fireEvent, render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({
  addProduct: vi.fn(),
  quickView: vi.fn(),
  user: { id: 7 },
}));

vi.mock("../hooks/useAuth", () => ({ useAuth: () => ({ user: mocks.user }) }));
vi.mock("../hooks/useCustomerCart", () => ({
  useCustomerCart: () => ({
    addProduct: mocks.addProduct,
    pendingActionKey: null,
  }),
}));

import { ProductItem } from "./productItem";

const baseProps = {
  productId: 5,
  image: "/tea.jpg",
  backgroundImage: "",
  label: "Ассам",
  brand: "TeaSeat",
  finalPrice: 300,
  originalPrice: 350,
  discountPercent: 10,
  measurement: {
    stockUnit: "gram" as const,
    saleStep: 50,
    priceUnitQuantity: 100,
  },
  availableQuantity: 160,
  isAvailable: true,
  onQuickView: mocks.quickView,
};

function renderCard(props = baseProps) {
  return render(<MemoryRouter><ProductItem {...props} /></MemoryRouter>);
}

beforeEach(() => {
  mocks.addProduct.mockReset().mockResolvedValue({ items: [] });
  mocks.quickView.mockReset();
});

describe("ProductItem quantity limits", () => {
  it("не поднимает количество выше продаваемой части остатка", () => {
    renderCard();
    expect(screen.getByText("Доступно: 150 г")).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Увеличить количество Ассам" }));
    expect(screen.getByText("150 г")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Увеличить количество Ассам" })).toBeDisabled();
  });

  it("отправляет backend только проверенное количество", async () => {
    renderCard();
    fireEvent.click(screen.getByRole("button", { name: "Увеличить количество Ассам" }));
    fireEvent.click(screen.getByRole("button", { name: "В корзину" }));
    expect(mocks.addProduct).toHaveBeenCalledWith({ product_id: 5, quantity: 150 });
  });

  it("блокирует покупку, если остаток меньше шага продажи", () => {
    renderCard({ ...baseProps, availableQuantity: 40 });
    expect(screen.getByRole("button", { name: "Нет в наличии" })).toBeDisabled();
    expect(screen.getByText("Доступно: 0 г")).toBeInTheDocument();
  });

  it("открывает краткий просмотр отдельной кнопкой", () => {
    renderCard();
    fireEvent.click(screen.getByRole("button", { name: "Быстрый просмотр Ассам" }));
    expect(mocks.quickView).toHaveBeenCalledOnce();
  });
});
