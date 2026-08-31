import { act, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { testBox, testQuote, testSizes } from "../components/constructor/testFixtures";
import type { BoxFloorSceneProps } from "../components/constructor/BoxFloorScene";
import type { SimplePackingSceneProps } from "../components/constructor/SimplePackingScene";

const mocks = vi.hoisted(() => ({
  user: { id: 7 } as { id: number } | null, loadOptions: vi.fn(), getBoxProducts: vi.fn(),
  quoteSimple: vi.fn(), validateAdvanced: vi.fn(), quoteAdvanced: vi.fn(), createSimpleGift: vi.fn(), createAdvancedGift: vi.fn(), addGift: vi.fn(),
}));
vi.mock("../ui/header/header", () => ({ default: () => null }));
vi.mock("../ui/footer/Footer", () => ({ default: () => null }));
vi.mock("../hooks/useAuth", () => ({ useAuth: () => ({ user: mocks.user, loading: false }) }));
vi.mock("../hooks/useCustomerCart", () => ({ useCustomerCart: () => ({ addGift: mocks.addGift }) }));
vi.mock("../api/giftConstructorAPI", () => ({ giftConstructorApi: mocks }));
vi.mock("../components/constructor/ConstructorBoxScene", () => ({ default: () => <div aria-label="Коробки в 3D" /> }));
vi.mock("../components/constructor/SimplePackingScene", () => ({ default: (props: SimplePackingSceneProps) => <div aria-label="Анимация упаковки подарка">{props.teas.length} + {props.sweets.length}</div> }));
vi.mock("../components/constructor/BoxFloorScene", async () => {
  const { default: FloorGrid } = await import("../components/constructor/FloorGrid");
  return { default: (props: BoxFloorSceneProps) => props.editing ? <FloorGrid {...props} /> : <button onClick={props.onChoose}>Модель выбранной коробки</button> };
});
import ConstructorPage from "./ConstructorPage";

let requestedMode: "simple" | "advanced" | null = "simple";
function renderPage(mode: "simple" | "advanced" | null = "simple") {
  requestedMode = mode;
  const view = render(<MemoryRouter><ConstructorPage /></MemoryRouter>);
  return view;
}
async function chooseBox(name = testBox.name) {
  const button = await screen.findByRole("button", { name: `Выбрать коробку ${name}` });
  await act(async () => { fireEvent.click(button); });
  if (requestedMode) await act(async () => { fireEvent.click(screen.getByRole("button", { name: requestedMode === "simple" ? "С анимацией" : "Вручную" })); });
}
const addTea = () => fireEvent.click(screen.getByRole("button", { name: "Добавить Ассам, 50 г" }));
const addSweet = () => fireEvent.click(screen.getByRole("button", { name: "Добавить Пастила, 50 г" }));
async function fillSimple() {
  await chooseBox();
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
  it("передаёт 5+2 в анимацию и сохраняет настоящий simple pipeline", async () => {
    renderPage();
    await fillSimple();
    expect(screen.queryByLabelText(/Дно коробки/)).not.toBeInTheDocument();
    expect(await screen.findByLabelText("Анимация упаковки подарка")).toHaveTextContent("5 + 2");
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
    renderPage("advanced"); await chooseBox();
    expect(screen.queryByRole("button", { name: "Выбрать коробку и расставить товары" })).not.toBeInTheDocument();
    await screen.findByLabelText("Дно коробки, вид сверху");
    addTea();
    fireEvent.click(screen.getByRole("button", { name: "Ячейка 3, 2" }));
    expect(screen.getByRole("button", { name: /^Позиция 1:/ })).toHaveStyle({ gridColumn: "3 / span 1" });
    expect(screen.queryByRole("spinbutton")).not.toBeInTheDocument();
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

  it("возврат из проверки сохраняет вкладку и передаёт поворот в boolean-контракте API", async () => {
    renderPage("advanced"); await chooseBox();
    await screen.findByLabelText("Дно коробки, вид сверху");
    fireEvent.click(screen.getByRole("tab", { name: /Сладости/ }));
    addSweet();
    fireEvent.click(screen.getByRole("button", { name: "Повернуть вправо на 90°" }));
    fireEvent.click(screen.getByRole("button", { name: "Проверить подарок и цену" }));
    await waitFor(() => expect(screen.getByRole("button", { name: "Добавить подарок в корзину" })).toBeEnabled());
    expect(mocks.validateAdvanced).toHaveBeenCalledWith({ box_profile_id: 4, items: [{
      client_item_id: expect.any(String), product_size_id: 21, position_x: 0, position_y: 0, is_rotated: true,
    }] }, expect.any(AbortSignal));
    fireEvent.click(screen.getByRole("button", { name: "Наполнение" }));
    expect(screen.getByRole("tab", { name: /Сладости/ })).toHaveAttribute("aria-selected", "true");
    expect(screen.getByLabelText("Количество Пастила, 50 г")).toHaveTextContent("1");
    expect(mocks.createAdvancedGift).not.toHaveBeenCalled();
  });

  it("возвращается из проверки к наполнению без потери выбранных форматов", async () => {
    renderPage(); await fillSimple();
    fireEvent.click(screen.getByRole("button", { name: "Проверить подарок и цену" }));
    await waitFor(() => expect(screen.getByRole("button", { name: "Добавить подарок в корзину" })).toBeEnabled());
    fireEvent.click(screen.getByRole("button", { name: "Наполнение" }));
    expect(screen.getByLabelText("Количество Ассам, 50 г")).toHaveTextContent("5");
    expect(screen.getByLabelText("Количество Пастила, 50 г")).toHaveTextContent("2");
    expect(mocks.createSimpleGift).not.toHaveBeenCalled();
    fireEvent.click(screen.getByRole("button", { name: "Проверить подарок и цену" }));
    await waitFor(() => expect(mocks.quoteSimple).toHaveBeenCalledTimes(2));
  });

  it("повторяет неудавшуюся загрузку без мокового fallback", async () => {
    mocks.loadOptions.mockRejectedValueOnce({ response: { status: 500 } });
    renderPage();
    fireEvent.click(await screen.findByRole("button", { name: "Повторить загрузку коробок" }));
    await chooseBox();
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
    await chooseBox();
    await waitFor(() => expect(mocks.getBoxProducts).toHaveBeenCalledOnce());
    const oldSignal = mocks.getBoxProducts.mock.calls[0][1] as AbortSignal;
    fireEvent.click(screen.getByRole("button", { name: "Коробка" }));
    await chooseBox(secondBox.name);
    await waitFor(() => expect(mocks.getBoxProducts).toHaveBeenCalledTimes(2));
    expect(oldSignal.aborted).toBe(true);
    await act(async () => resolveOld({ box: testBox, product_sizes: testSizes }));
    expect(screen.queryByRole("button", { name: "Добавить Ассам, 50 г" })).not.toBeInTheDocument();
    expect(screen.getByRole("heading", { name: /^Чай/ })).toBeInTheDocument();
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
    renderPage("advanced"); await chooseBox();
    await screen.findByText(/В общем каталоге этого режима тоже нет доступных форматов/);
    await screen.findByLabelText("Дно коробки, вид сверху");
    expect(screen.getByLabelText("Заполнение коробки")).toHaveTextContent("0 / 40");
    expect(screen.queryByRole("button", { name: /Добавить Ассам/ })).not.toBeInTheDocument();
    expect(mocks.createAdvancedGift).not.toHaveBeenCalled();
  });

  it("не подставляет общий каталог, если backend отсеял форматы коробки", async () => {
    mocks.getBoxProducts.mockResolvedValue({ box: testBox, product_sizes: [] });
    renderPage("advanced"); await chooseBox();
    await screen.findByText(/доступно 2 форматов, но для этой коробки не подошёл ни один/);
    await screen.findByLabelText("Дно коробки, вид сверху");
    expect(screen.queryByRole("button", { name: /Добавить Ассам/ })).not.toBeInTheDocument();
    mocks.getBoxProducts.mockResolvedValue({ box: testBox, product_sizes: testSizes });
    fireEvent.click(screen.getByRole("button", { name: "Обновить каталог конструктора" }));
    await screen.findByRole("button", { name: "Добавить Ассам, 50 г" });
    expect(screen.queryByLabelText("Диагностика каталога конструктора")).not.toBeInTheDocument();
  });

  it("возвращает выбор способа после смены размера и открывает новое дно", async () => {
    const second = { ...testBox, id: 5, name: "Другая коробка" };
    mocks.loadOptions.mockResolvedValue({ boxes: [testBox, second], product_sizes: testSizes });
    mocks.getBoxProducts.mockImplementation((id: number) => Promise.resolve({ box: id === 4 ? testBox : second, product_sizes: testSizes }));
    renderPage("advanced"); await chooseBox();
    await screen.findByLabelText("Дно коробки, вид сверху");
    fireEvent.click(screen.getByRole("button", { name: "Коробка" }));
    requestedMode = null;
    await chooseBox(second.name);
    expect(screen.getByRole("button", { name: "Вручную" })).toBeInTheDocument();
    expect(screen.queryByLabelText("Дно коробки, вид сверху")).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Вручную" }));
    await screen.findByLabelText("Дно коробки, вид сверху");
  });

  it("сначала загружает коробки в 3D, затем показывает два способа сборки", async () => {
    renderPage(null);
    await screen.findByRole("button", { name: `Выбрать коробку ${testBox.name}` });
    expect(mocks.loadOptions).toHaveBeenCalledWith("advanced", expect.any(AbortSignal));
    expect(screen.queryByRole("button", { name: "С анимацией" })).not.toBeInTheDocument();
    expect(mocks.getBoxProducts).not.toHaveBeenCalled();
    expect(screen.queryByRole("combobox")).not.toBeInTheDocument();
    await chooseBox();
    expect(screen.getByRole("button", { name: "С анимацией" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Вручную" })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Добавить Ассам, 50 г" })).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "С анимацией" }));
    await screen.findByRole("button", { name: "Добавить Ассам, 50 г" });
    expect(mocks.getBoxProducts).toHaveBeenCalledWith(testBox.id, expect.any(AbortSignal));
  });

  it("возврат к карточкам и выбор той же коробки сохраняет состав", async () => {
    renderPage(); await fillSimple();
    fireEvent.click(screen.getByRole("button", { name: "Коробка" }));
    await chooseBox();
    expect(screen.getByLabelText("Количество Ассам, 50 г")).toHaveTextContent("5");
    expect(screen.getByLabelText("Количество Пастила, 50 г")).toHaveTextContent("2");
  });

  it("меняет способ, сохраняя оба черновика и не загружая каталог заново", async () => {
    renderPage(); await fillSimple();
    fireEvent.click(screen.getByRole("button", { name: "Назад" }));
    expect(screen.queryByLabelText("Анимация упаковки подарка")).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Вручную" }));
    await screen.findByLabelText("Дно коробки, вид сверху");
    addTea();
    fireEvent.click(screen.getByRole("button", { name: "Назад" }));
    fireEvent.click(screen.getByRole("button", { name: "С анимацией" }));
    expect(screen.getByLabelText("Количество Ассам, 50 г")).toHaveTextContent("5");
    fireEvent.click(screen.getByRole("button", { name: "Назад" }));
    fireEvent.click(screen.getByRole("button", { name: "Вручную" }));
    expect(screen.getByLabelText("Количество Ассам, 50 г")).toHaveTextContent("1");
    fireEvent.click(screen.getByRole("button", { name: "Очистить выбор" }));
    fireEvent.click(screen.getByRole("button", { name: "Назад" }));
    fireEvent.click(screen.getByRole("button", { name: "С анимацией" }));
    expect(screen.getByLabelText("Количество Ассам, 50 г")).toHaveTextContent("5");
    expect(mocks.getBoxProducts).toHaveBeenCalledOnce();
    expect(mocks.createSimpleGift).not.toHaveBeenCalled();
    expect(mocks.createAdvancedGift).not.toHaveBeenCalled();
  });

  it("позволяет вернуться во время загрузки товаров", async () => {
    mocks.getBoxProducts.mockReturnValue(new Promise(() => {}));
    renderPage(); await chooseBox();
    expect(screen.getByText("Загружаем товары…")).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Назад" }));
    expect(screen.getByRole("button", { name: "Вручную" })).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Назад" }));
    expect(screen.getByRole("button", { name: `Выбрать коробку ${testBox.name}` })).toBeInTheDocument();
  });

  it("не разрешает анимационный режим коробке без simple-настроек", async () => {
    const advancedOnly = { ...testBox, simple_constructor_enabled: false, simple_requirements: null };
    mocks.loadOptions.mockResolvedValue({ boxes: [advancedOnly], product_sizes: testSizes });
    mocks.getBoxProducts.mockResolvedValue({ box: advancedOnly, product_sizes: testSizes });
    renderPage(null); await chooseBox();
    expect(screen.getByRole("button", { name: "С анимацией" })).toBeDisabled();
    expect(screen.getByRole("button", { name: "Вручную" })).toBeEnabled();
  });

  it("не теряет созданный Gift, если при reconnect коробка исчезла из options", async () => {
    mocks.addGift.mockRejectedValue(new Error("Network unavailable"));
    renderPage(); await fillSimple();
    fireEvent.click(screen.getByRole("button", { name: "Проверить подарок и цену" }));
    await waitFor(() => expect(screen.getByRole("button", { name: "Добавить подарок в корзину" })).toBeEnabled());
    fireEvent.click(screen.getByRole("button", { name: "Добавить подарок в корзину" }));
    await screen.findByRole("button", { name: "Повторить добавление в корзину" });
    act(() => { Object.defineProperty(navigator, "onLine", { configurable: true, value: false }); window.dispatchEvent(new Event("offline")); });
    mocks.loadOptions.mockResolvedValue({ boxes: [], product_sizes: [] });
    mocks.getBoxProducts.mockRejectedValue({ response: { status: 404 } });
    act(() => { Object.defineProperty(navigator, "onLine", { configurable: true, value: true }); window.dispatchEvent(new Event("online")); });
    await waitFor(() => expect(mocks.loadOptions).toHaveBeenCalledTimes(2));
    const retry = await screen.findByRole("button", { name: "Повторить добавление в корзину" });
    await waitFor(() => expect(retry).toBeEnabled());
    mocks.addGift.mockResolvedValue({});
    fireEvent.click(retry);
    await screen.findByText("Подарок добавлен в корзину.");
    expect(mocks.createSimpleGift).toHaveBeenCalledOnce();
    expect(mocks.addGift.mock.calls[1][0]).toEqual(mocks.addGift.mock.calls[0][0]);
  });
});
