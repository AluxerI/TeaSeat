import { act, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { testBox, testQuote, testSizes } from "../components/constructor/testFixtures";
import type { BoxFloorSceneProps } from "../components/constructor/BoxFloorScene";

const mocks = vi.hoisted(() => ({
  user: { id: 7 } as { id: number } | null, loadOptions: vi.fn(), getBoxProducts: vi.fn(),
  quoteSimple: vi.fn(), validateAdvanced: vi.fn(), quoteAdvanced: vi.fn(), createSimpleGift: vi.fn(), createAdvancedGift: vi.fn(), addGift: vi.fn(),
}));
vi.mock("../ui/header/header", () => ({ default: () => null }));
vi.mock("../ui/footer/Footer", () => ({ default: () => null }));
vi.mock("../hooks/useAuth", () => ({ useAuth: () => ({ user: mocks.user, loading: false }) }));
vi.mock("../hooks/useCustomerCart", () => ({ useCustomerCart: () => ({ addGift: mocks.addGift }) }));
vi.mock("../api/giftConstructorAPI", () => ({ giftConstructorApi: mocks }));
vi.mock("../components/constructor/BoxFloorScene", async () => {
  const { default: FloorGrid } = await import("../components/constructor/FloorGrid");
  return { default: (props: BoxFloorSceneProps) => props.editing ? <FloorGrid {...props} /> : <button onClick={props.onChoose}>Модель выбранной коробки</button> };
});
import ConstructorPage from "./ConstructorPage";

function renderPage() { return render(<MemoryRouter><ConstructorPage /></MemoryRouter>); }
const addTea = () => fireEvent.click(screen.getByRole("button", { name: "Добавить Ассам, 50 г" }));
const addSweet = () => fireEvent.click(screen.getByRole("button", { name: "Добавить Пастила, 50 г" }));
async function fillSimple() {
  await screen.findByRole("button", { name: "Добавить Ассам, 50 г" });
  for (let index = 0; index < 5; index += 1) addTea();
  addSweet(); addSweet();
}
beforeEach(() => {
  vi.clearAllMocks();
  mocks.user = { id: 7 };
  Object.defineProperty(navigator, "onLine", { configurable: true, value: true });
  mocks.loadOptions.mockReset().mockResolvedValue({ boxes: [testBox], product_sizes: testSizes });
  mocks.getBoxProducts.mockReset().mockResolvedValue({ box: testBox, product_sizes: testSizes });
  mocks.quoteSimple.mockReset().mockResolvedValue(testQuote);
  mocks.quoteAdvanced.mockReset().mockResolvedValue(testQuote);
  mocks.validateAdvanced.mockReset().mockResolvedValue(undefined);
  mocks.createSimpleGift.mockReset().mockResolvedValue({ id: 40, version: 1 });
  mocks.createAdvancedGift.mockReset().mockResolvedValue({ id: 41, version: 2 });
  mocks.addGift.mockReset().mockResolvedValue({ id: 9, items: [], gifts: [] });
});

describe("constructor workspace", () => {
  it("не монтирует 3D в простом режиме и принимает коробку 5+2", async () => {
    renderPage();
    await fillSimple();
    expect(screen.queryByLabelText(/Дно коробки/)).not.toBeInTheDocument();
    expect(document.querySelector("canvas")).toBeNull();
    expect(screen.getByRole("button", { name: "Добавить Ассам, 50 г" })).toBeDisabled();
    fireEvent.click(screen.getByRole("button", { name: "Проверить подарок и цену" }));
    const submit = await screen.findByRole("button", { name: "Добавить подарок в корзину" });
    await waitFor(() => expect(submit).toBeEnabled());
    expect(mocks.quoteSimple).toHaveBeenCalledWith({ box_profile_id: 4, tea_product_size_ids: [11, 11, 11, 11, 11], sweet_product_size_ids: [21, 21], quantity: 1 }, expect.any(AbortSignal));
    expect(mocks.createSimpleGift).not.toHaveBeenCalled();
    fireEvent.click(submit);
    await screen.findByText("Подарок добавлен в корзину.");
    expect(mocks.addGift).toHaveBeenCalledWith(expect.objectContaining({ gift_id: 40, gift_version: 1 }));
  });

  it("переключает сложный режим на сетку и проверяет advanced pipeline", async () => {
    renderPage();
    fireEvent.click(screen.getByRole("button", { name: "Сложный — сетка 2.5D" }));
    const choose = await screen.findByRole("button", { name: "Выбрать коробку и расставить товары" });
    expect(screen.queryByLabelText("Дно коробки, вид сверху")).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Добавить Ассам, 50 г" })).not.toBeInTheDocument();
    fireEvent.click(choose);
    await screen.findByLabelText("Дно коробки, вид сверху");
    addTea();
    fireEvent.click(screen.getByRole("button", { name: "Ячейка 3, 2" }));
    expect(screen.getByRole("spinbutton", { name: "Столбец позиции" })).toHaveValue(3);
    fireEvent.click(screen.getByRole("button", { name: "Проверить подарок и цену" }));
    const submit = await screen.findByRole("button", { name: "Добавить подарок в корзину" });
    await waitFor(() => expect(submit).toBeEnabled());
    expect(mocks.validateAdvanced).toHaveBeenCalledWith({ box_profile_id: 4, items: [expect.objectContaining({ product_size_id: 11, position_x: 2, position_y: 1, is_rotated: false })] }, expect.any(AbortSignal));
    expect(mocks.quoteSimple).not.toHaveBeenCalled();
    fireEvent.click(submit);
    await screen.findByText("Подарок добавлен в корзину.");
    expect(mocks.createAdvancedGift).toHaveBeenCalledOnce();
  });

  it("объясняет обязательный вход и не запрашивает options анонимно", () => {
    mocks.user = null;
    renderPage();
    expect(screen.getByRole("link", { name: "Войти" })).toHaveAttribute("href", "/login");
    expect(mocks.loadOptions).not.toHaveBeenCalled();
  });

  it("повторяет неудавшуюся загрузку без мокового fallback", async () => {
    mocks.loadOptions.mockRejectedValueOnce({ response: { status: 500 } });
    renderPage();
    fireEvent.click(await screen.findByRole("button", { name: "Повторить загрузку коробок" }));
    await screen.findByRole("button", { name: "Добавить Ассам, 50 г" });
    expect(mocks.loadOptions).toHaveBeenCalledTimes(2);
  });

  it("показывает пустые настройки, а не вечную загрузку", async () => {
    mocks.loadOptions.mockResolvedValue({ boxes: [], product_sizes: testSizes });
    renderPage();
    expect(await screen.findByText(/нет активных коробок/)).toBeInTheDocument();
    expect(mocks.getBoxProducts).not.toHaveBeenCalled();
  });

  it("сохраняет выбор при offline/reconnect и не создаёт подарок автоматически", async () => {
    renderPage();
    await fillSimple();
    act(() => { Object.defineProperty(navigator, "onLine", { configurable: true, value: false }); window.dispatchEvent(new Event("offline")); });
    expect(screen.getByLabelText("Количество Ассам, 50 г")).toHaveTextContent("5");
    act(() => { Object.defineProperty(navigator, "onLine", { configurable: true, value: true }); window.dispatchEvent(new Event("online")); });
    await waitFor(() => expect(mocks.getBoxProducts).toHaveBeenCalledTimes(2));
    expect(screen.getByLabelText("Количество Ассам, 50 г")).toHaveTextContent("5");
    expect(mocks.createSimpleGift).not.toHaveBeenCalled();
  });

  it("не принимает старый ответ товаров после смены коробки", async () => {
    const secondBox = { ...testBox, id: 5, name: "Другая коробка" };
    let resolveOld!: (value: unknown) => void;
    mocks.loadOptions.mockResolvedValue({ boxes: [testBox, secondBox], product_sizes: testSizes });
    mocks.getBoxProducts.mockImplementation((id: number) => id === 4
      ? new Promise((resolve) => { resolveOld = resolve; })
      : Promise.resolve({ box: secondBox, product_sizes: [] }));
    renderPage();
    const select = await screen.findByRole("combobox", { name: "Коробка" });
    await waitFor(() => expect(mocks.getBoxProducts).toHaveBeenCalledOnce());
    const oldSignal = mocks.getBoxProducts.mock.calls[0][1] as AbortSignal;
    fireEvent.change(select, { target: { value: "5" } });
    await waitFor(() => expect(mocks.getBoxProducts).toHaveBeenCalledTimes(2));
    expect(oldSignal.aborted).toBe(true);
    await act(async () => resolveOld({ box: testBox, product_sizes: testSizes }));
    expect(screen.queryByRole("button", { name: "Добавить Ассам, 50 г" })).not.toBeInTheDocument();
    expect(select).toHaveValue("5");
  });

  it("позволяет очистить выбор форматов, удалённых из обновлённого каталога", async () => {
    renderPage(); await fillSimple();
    act(() => { Object.defineProperty(navigator, "onLine", { configurable: true, value: false }); window.dispatchEvent(new Event("offline")); });
    mocks.getBoxProducts.mockResolvedValue({ box: testBox, product_sizes: [] });
    act(() => { Object.defineProperty(navigator, "onLine", { configurable: true, value: true }); window.dispatchEvent(new Event("online")); });
    await screen.findByText(/Часть выбранных форматов больше недоступна/);
    expect(screen.getByRole("button", { name: "Проверить подарок и цену" })).toBeDisabled();
    fireEvent.click(screen.getByRole("button", { name: "Очистить выбор" }));
    expect(screen.queryByText(/Часть выбранных форматов больше недоступна/)).not.toBeInTheDocument();
  });

  it.each([[401, /Сессия истекла/], [403, /Нет доступа/], [429, /Слишком много запросов/]] as const)("показывает понятную ошибку %s", async (status, message) => {
    mocks.loadOptions.mockRejectedValue({ response: { status } });
    renderPage();
    expect(await screen.findByText(message)).toBeInTheDocument();
    expect(mocks.getBoxProducts).not.toHaveBeenCalled();
  });

  it("объясняет отсутствие ProductSize, не выдавая лимит 40 за количество товаров", async () => {
    mocks.loadOptions.mockResolvedValue({ boxes: [testBox], product_sizes: [] });
    mocks.getBoxProducts.mockResolvedValue({ box: testBox, product_sizes: [] });
    renderPage();
    fireEvent.click(screen.getByRole("button", { name: "Сложный — сетка 2.5D" }));
    await screen.findByText(/В общем каталоге этого режима тоже нет доступных форматов/);
    fireEvent.click(await screen.findByRole("button", { name: "Выбрать коробку и расставить товары" }));
    expect(screen.getByText("0 выбрано · максимум 40")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /Добавить Ассам/ })).not.toBeInTheDocument();
    expect(mocks.createAdvancedGift).not.toHaveBeenCalled();
  });

  it("не подставляет общий каталог, если backend отсеял форматы коробки", async () => {
    mocks.getBoxProducts.mockResolvedValue({ box: testBox, product_sizes: [] });
    renderPage();
    fireEvent.click(screen.getByRole("button", { name: "Сложный — сетка 2.5D" }));
    await screen.findByText(/доступно 2 форматов, но для этой коробки не подошёл ни один/);
    fireEvent.click(await screen.findByRole("button", { name: "Выбрать коробку и расставить товары" }));
    expect(screen.queryByRole("button", { name: /Добавить Ассам/ })).not.toBeInTheDocument();
    mocks.getBoxProducts.mockResolvedValue({ box: testBox, product_sizes: testSizes });
    fireEvent.click(screen.getByRole("button", { name: "Обновить каталог конструктора" }));
    await screen.findByRole("button", { name: "Добавить Ассам, 50 г" });
    expect(screen.queryByLabelText("Диагностика каталога конструктора")).not.toBeInTheDocument();
  });

  it("возвращает предпросмотр до подтверждения другой коробки", async () => {
    const second = { ...testBox, id: 5, name: "Другая коробка" };
    mocks.loadOptions.mockResolvedValue({ boxes: [testBox, second], product_sizes: testSizes });
    mocks.getBoxProducts.mockImplementation((id: number) => Promise.resolve({ box: id === 4 ? testBox : second, product_sizes: testSizes }));
    renderPage();
    fireEvent.click(screen.getByRole("button", { name: "Сложный — сетка 2.5D" }));
    fireEvent.click(await screen.findByRole("button", { name: "Модель выбранной коробки" }));
    await screen.findByLabelText("Дно коробки, вид сверху");
    fireEvent.change(screen.getByRole("combobox", { name: "Коробка" }), { target: { value: "5" } });
    await screen.findByRole("button", { name: "Выбрать коробку и расставить товары" });
    expect(screen.queryByLabelText("Дно коробки, вид сверху")).not.toBeInTheDocument();
  });
});
