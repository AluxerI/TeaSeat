import { act, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { ConstructorDraft } from "../../interfaces/giftConstructor";
import { testBox, testQuote, testSizes } from "./testFixtures";

const mocks = vi.hoisted(() => ({ user: { id: 7 } as { id: number } | null, quoteSimple: vi.fn(), validateAdvanced: vi.fn(), quoteAdvanced: vi.fn(), createSimpleGift: vi.fn(), createAdvancedGift: vi.fn(), addGift: vi.fn(), onBusy: vi.fn() }));
vi.mock("../../hooks/useAuth", () => ({ useAuth: () => ({ user: mocks.user }) }));
vi.mock("../../hooks/useCustomerCart", () => ({ useCustomerCart: () => ({ addGift: mocks.addGift }) }));
vi.mock("../../api/giftConstructorAPI", () => ({ giftConstructorApi: mocks }));
import GiftConfirmation from "./GiftConfirmation";

const draft: ConstructorDraft = { mode: "simple", selection: { box_profile_id: 4, tea_product_size_ids: [11], sweet_product_size_ids: [21] } };
const tree = () => <MemoryRouter><GiftConfirmation draft={draft} box={testBox} contents={testSizes} onBack={vi.fn()} onReset={vi.fn()} onBusy={mocks.onBusy} /></MemoryRouter>;
async function submitButton() {
  const button = screen.getByRole("button", { name: "Добавить подарок в корзину" });
  await waitFor(() => expect(button).toBeEnabled());
  return button;
}
function online(value: boolean) {
  act(() => { Object.defineProperty(navigator, "onLine", { configurable: true, value }); window.dispatchEvent(new Event(value ? "online" : "offline")); });
}
beforeEach(() => {
  vi.clearAllMocks(); mocks.user = { id: 7 };
  Object.defineProperty(navigator, "onLine", { configurable: true, value: true });
  mocks.quoteSimple.mockReset().mockResolvedValue(testQuote);
  mocks.createSimpleGift.mockReset().mockResolvedValue({ id: 40, version: 1 });
  mocks.addGift.mockReset().mockResolvedValue({ id: 9 });
});

describe("shared gift confirmation", () => {
  it("блокирует двойное нажатие во время создания", async () => {
    let resolve!: (gift: { id: number; version: number }) => void;
    mocks.createSimpleGift.mockReturnValue(new Promise((done) => { resolve = done; }));
    render(tree());
    const button = await submitButton();
    fireEvent.click(button); fireEvent.click(button);
    expect(mocks.createSimpleGift).toHaveBeenCalledOnce();
    expect(mocks.onBusy).toHaveBeenCalledWith(true);
    await act(async () => resolve({ id: 40, version: 1 }));
    await screen.findByText("Подарок добавлен в корзину.");
    expect(mocks.addGift).toHaveBeenCalledOnce();
    expect(mocks.onBusy).toHaveBeenLastCalledWith(false);
  });

  it("повторяет только cart/gifts с тем же UUID после сетевого сбоя", async () => {
    mocks.addGift.mockRejectedValueOnce(new Error("network"));
    render(tree()); fireEvent.click(await submitButton());
    const retry = await screen.findByRole("button", { name: "Повторить добавление в корзину" });
    const firstRequest = mocks.addGift.mock.calls[0][0];
    expect(firstRequest.client_instance_id).toMatch(/^[\da-f]{8}-[\da-f]{4}-4[\da-f]{3}-[89ab][\da-f]{3}-[\da-f]{12}$/i);
    expect(screen.getByRole("button", { name: "К составу" })).toBeDisabled();
    fireEvent.click(retry);
    await screen.findByText("Подарок добавлен в корзину.");
    expect(mocks.createSimpleGift).toHaveBeenCalledOnce();
    expect(mocks.addGift).toHaveBeenLastCalledWith(firstRequest);
  });

  it("не повторяет создание с неизвестным результатом", async () => {
    mocks.createSimpleGift.mockRejectedValue(new Error("timeout"));
    render(tree()); fireEvent.click(await submitButton());
    await screen.findByText(/Сервер мог сохранить подарок/);
    expect(screen.getByRole("button", { name: "Добавить подарок в корзину" })).toBeDisabled();
    expect(mocks.addGift).not.toHaveBeenCalled();
  });

  it("не отправляет запись offline и не сохраняет автоматически после reconnect", async () => {
    render(tree()); await submitButton();
    online(false);
    expect(screen.getByRole("button", { name: "Добавить подарок в корзину" })).toBeDisabled();
    expect(screen.getByText(/не отправляются в офлайн-очередь/)).toBeInTheDocument();
    online(true); await submitButton();
    expect(mocks.quoteSimple).toHaveBeenCalledTimes(2);
    expect(mocks.createSimpleGift).not.toHaveBeenCalled();
  });

  it("не продолжает pipeline от предыдущего аккаунта", async () => {
    let resolve!: (gift: { id: number; version: number }) => void;
    mocks.createSimpleGift.mockReturnValue(new Promise((done) => { resolve = done; }));
    const view = render(tree()); fireEvent.click(await submitButton());
    mocks.user = { id: 8 }; view.rerender(tree());
    await act(async () => resolve({ id: 40, version: 1 }));
    expect(mocks.addGift).not.toHaveBeenCalled();
    expect(screen.queryByText("Подарок добавлен в корзину.")).not.toBeInTheDocument();
  });

  it("не начинает cart POST, если сеть пропала во время создания Gift", async () => {
    let resolve!: (gift: { id: number; version: number }) => void;
    mocks.createSimpleGift.mockReturnValue(new Promise((done) => { resolve = done; }));
    render(tree()); fireEvent.click(await submitButton());
    online(false);
    await act(async () => resolve({ id: 40, version: 1 }));
    expect(mocks.addGift).not.toHaveBeenCalled();
    expect(screen.getByRole("button", { name: "Повторить добавление в корзину" })).toBeDisabled();
    online(true);
    await waitFor(() => expect(screen.getByRole("button", { name: "Повторить добавление в корзину" })).toBeEnabled());
    fireEvent.click(screen.getByRole("button", { name: "Повторить добавление в корзину" }));
    await screen.findByText("Подарок добавлен в корзину.");
    expect(mocks.createSimpleGift).toHaveBeenCalledOnce();
  });

  it("показывает ошибку quote текстом, без HTML-инъекции", async () => {
    const payload = '<svg onload="alert(1)"><script>alert(1)</script></svg>';
    mocks.quoteSimple.mockRejectedValueOnce({ response: { status: 422, data: { message: payload } } });
    render(tree());
    expect(await screen.findByRole("alert")).toHaveTextContent(payload);
    expect(document.querySelector("svg, script")).toBeNull();
    expect(screen.getByRole("button", { name: "Добавить подарок в корзину" })).toBeDisabled();
    fireEvent.click(screen.getByRole("button", { name: "Повторить расчёт" }));
    await submitButton();
    expect(mocks.createSimpleGift).not.toHaveBeenCalled();
  });
});
