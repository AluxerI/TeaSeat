import { act, fireEvent, render, screen, within } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { BoxFloorSceneProps } from "./BoxFloorScene";
import { testBox, testSize, testSizes } from "./testFixtures";
import { CATALOG_HOLD_MS } from "../../hooks/useConstructorDrag";

vi.mock("./BoxFloorScene", async () => {
  const { default: FloorGrid } = await import("./FloorGrid");
  return { default: (props: BoxFloorSceneProps) => props.editing ? <div data-testid="ready-floor"><FloorGrid {...props} /></div> : null };
});
import ConstructorEditor from "./ConstructorEditor";

beforeEach(() => {
  class TestPointerEvent extends MouseEvent {
    pointerId: number;
    isPrimary: boolean;
    pointerType: string;
    constructor(type: string, options: PointerEventInit = {}) {
      super(type, options); this.pointerId = options.pointerId ?? 1; this.isPrimary = options.isPrimary ?? true;
      this.pointerType = options.pointerType ?? "mouse";
    }
  }
  vi.stubGlobal("PointerEvent", TestPointerEvent);
});
afterEach(() => { vi.useRealTimers(); vi.unstubAllGlobals(); vi.restoreAllMocks(); });

async function editor(sizes = testSizes) {
  render(<ConstructorEditor mode="advanced" box={testBox} sizes={sizes} onBusy={vi.fn()} cellSizeMm={10} />);
  fireEvent.click(screen.getByRole("button", { name: "Выбрать коробку и расставить товары" }));
  await screen.findByTestId("ready-floor");
  const surface = await screen.findByLabelText("Дно коробки, вид сверху");
  vi.spyOn(surface, "getBoundingClientRect").mockReturnValue({ left: 100, top: 100, width: 400, height: 300, right: 500, bottom: 400, x: 100, y: 100, toJSON: () => ({}) });
  return surface;
}
const pointer = (x: number, y: number) => ({ clientX: x, clientY: y, pointerId: 1, button: 0, bubbles: true });
const addTea = () => fireEvent.click(screen.getByRole("button", { name: "Добавить Ассам, 50 г" }));
const teaCard = () => screen.getByRole("article", { name: "Ассам, 50 г" });
const touch = (x: number, y: number) => ({ identifier: 7, clientX: x, clientY: y });
const touchStart = (element: Element, x = 650, y = 150) => fireEvent.touchStart(element, { touches: [touch(x, y)], changedTouches: [touch(x, y)] });
const touchMove = (x: number, y: number) => fireEvent.touchMove(window, { touches: [touch(x, y)], changedTouches: [touch(x, y)] });
const touchEnd = (x: number, y: number) => fireEvent.touchEnd(window, { touches: [], changedTouches: [touch(x, y)] });

describe("constructor pointer drag", () => {
  it("перетаскивает из каталога с предпросмотром размера и без двойного добавления", async () => {
    await editor();
    const handle = teaCard();
    fireEvent.pointerDown(handle, pointer(650, 150));
    fireEvent.pointerMove(window, pointer(350, 250));
    expect(screen.getByText("Можно разместить")).toBeInTheDocument();
    expect(screen.queryByText(/10 × 10 мм/)).not.toBeInTheDocument();
    const ghost = document.querySelector('[aria-hidden="true"] img[src*="tea.svg"]');
    expect(ghost).toBeInTheDocument();
    fireEvent.pointerUp(window, pointer(350, 250));
    fireEvent.click(handle);
    expect(screen.getAllByRole("button", { name: /^Позиция \d/ })).toHaveLength(1);
    expect(screen.getByRole("button", { name: /^Позиция 1:/ })).toHaveStyle({ gridColumn: "3 / span 1", gridRow: "2 / span 1" });
    expect(screen.queryByRole("spinbutton")).not.toBeInTheDocument();
  });

  it("двигает существующую позицию с сохранением точки захвата", async () => {
    await editor(); addTea();
    fireEvent.pointerDown(screen.getByRole("button", { name: /^Позиция 1:/ }), pointer(175, 125));
    fireEvent.pointerMove(window, pointer(375, 225));
    fireEvent.pointerUp(window, pointer(375, 225));
    expect(screen.getByRole("button", { name: /^Позиция 1:/ })).toHaveStyle({ gridColumn: "3 / span 1", gridRow: "2 / span 1" });
  });

  it("не меняет координаты при перекрытии", async () => {
    await editor(); addTea();
    fireEvent.click(screen.getByRole("tab", { name: /Сладости/ }));
    fireEvent.click(screen.getByRole("button", { name: "Добавить Пастила, 50 г" }));
    fireEvent.pointerDown(screen.getByRole("button", { name: /^Позиция 1:/ }), pointer(150, 150));
    fireEvent.pointerMove(window, pointer(250, 150));
    expect(screen.getByText(/Позиции перекрываются/)).toBeInTheDocument();
    fireEvent.pointerUp(window, pointer(250, 150));
    expect(screen.getByRole("button", { name: /^Позиция 1:/ })).toHaveStyle({ gridColumn: "1 / span 1" });
  });

  it.each(["escape", "pointercancel", "lostcapture", "outside"])("сохраняет прежнее положение: %s", async (reason) => {
    await editor(); addTea();
    fireEvent.pointerDown(screen.getByRole("button", { name: /^Позиция 1:/ }), pointer(150, 150));
    fireEvent.pointerMove(window, pointer(350, 250));
    if (reason === "escape") fireEvent.keyDown(window, { key: "Escape" });
    if (reason === "pointercancel") fireEvent.pointerCancel(window, pointer(350, 250));
    if (reason === "lostcapture") fireEvent.lostPointerCapture(window, pointer(350, 250));
    fireEvent.pointerUp(window, pointer(reason === "outside" ? 800 : 350, 250));
    expect(screen.getByRole("button", { name: /^Позиция 1:/ })).toHaveStyle({ gridColumn: "1 / span 1", gridRow: "1 / span 1" });
  });

  it("сохраняет расстановку при возврате к коробке по шагам", async () => {
    await editor([testSize(11), { ...testSize(12), label: "100 г" }]); addTea();
    act(() => screen.getByRole("article", { name: "Ассам, 100 г" }).focus());
    fireEvent.click(screen.getByRole("button", { name: "Коробка" }));
    expect(screen.queryByLabelText("Дно коробки, вид сверху")).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Выбрать коробку и расставить товары" }));
    expect(await screen.findByRole("button", { name: /^Позиция 1:/ })).toBeInTheDocument();
    expect(screen.getByRole("article", { name: "Ассам, 100 г" })).toBeInTheDocument();
  });

  it("группирует каталог и фильтрует форматы без изменения состава", async () => {
    await editor(); addTea();
    fireEvent.click(screen.getByRole("tab", { name: /Сладости/ }));
    fireEvent.change(screen.getByRole("searchbox", { name: "Найти товар или формат" }), { target: { value: "Пастила" } });
    expect(screen.queryByRole("button", { name: "Добавить Ассам, 50 г" })).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Добавить Пастила, 50 г" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /^Позиция 1:/ })).toBeInTheDocument();
  });

  it("клик по карточке не добавляет предмет; вложенная кнопка не начинает drag", async () => {
    await editor();
    fireEvent.click(teaCard());
    expect(screen.queryByRole("button", { name: /^Позиция 1:/ })).not.toBeInTheDocument();
    const add = screen.getByRole("button", { name: "Добавить Ассам, 50 г" });
    fireEvent.pointerDown(add, pointer(650, 150));
    fireEvent.pointerMove(window, pointer(350, 250));
    fireEvent.pointerUp(window, pointer(350, 250));
    expect(screen.queryByText("Можно разместить")).not.toBeInTheDocument();
    fireEvent.click(add);
    expect(screen.getAllByRole("button", { name: /^Позиция \d/ })).toHaveLength(1);
  });

  it("touch-свайп листает карусель, но не добавляет товар", async () => {
    await editor([testSize(11), { ...testSize(12), label: "100 г" }]);
    vi.useFakeTimers();
    touchStart(teaCard());
    touchMove(580, 152);
    act(() => vi.advanceTimersByTime(CATALOG_HOLD_MS + 20));
    touchEnd(570, 152);
    expect(screen.getByRole("article", { name: "Ассам, 100 г" })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /^Позиция 1:/ })).not.toBeInTheDocument();
  });

  it("touch-удержание активирует перенос всей карточки с размером", async () => {
    await editor(); vi.useFakeTimers();
    fireEvent.pointerDown(teaCard(), { ...pointer(650, 150), pointerType: "touch" });
    touchStart(teaCard());
    act(() => vi.advanceTimersByTime(CATALOG_HOLD_MS));
    expect(screen.getByRole("tab", { name: /Сладости/ })).toBeDisabled();
    touchMove(350, 250);
    expect(screen.getByText("Можно разместить")).toBeInTheDocument();
    touchEnd(350, 250);
    expect(screen.getAllByRole("button", { name: /^Позиция \d/ })).toHaveLength(1);
    expect(screen.getByRole("button", { name: /^Позиция 1:/ })).toHaveStyle({ gridColumn: "3 / span 1" });
  });

  it("вертикальный touch-жест оставляет скролл браузеру и отменяет удержание", async () => {
    await editor(); vi.useFakeTimers(); touchStart(teaCard());
    expect(touchMove(652, 195)).toBe(true); // событие не preventDefault
    act(() => vi.advanceTimersByTime(CATALOG_HOLD_MS + 20));
    touchMove(350, 250); touchEnd(350, 250);
    expect(screen.queryByRole("button", { name: /^Позиция 1:/ })).not.toBeInTheDocument();
    expect(screen.getByRole("tab", { name: /Сладости/ })).toBeEnabled();
  });

  it.each(["cancel", "escape", "multitouch", "blur"])("отменяет touch-удержание: %s", async (reason) => {
    await editor(); vi.useFakeTimers(); touchStart(teaCard());
    if (reason === "cancel") fireEvent.touchCancel(window, { changedTouches: [touch(650, 150)] });
    if (reason === "escape") fireEvent.keyDown(window, { key: "Escape" });
    if (reason === "multitouch") fireEvent.touchStart(window, { touches: [touch(650, 150), { ...touch(660, 150), identifier: 8 }] });
    if (reason === "blur") fireEvent.blur(window);
    act(() => vi.advanceTimersByTime(CATALOG_HOLD_MS + 20));
    touchMove(350, 250); touchEnd(350, 250);
    expect(screen.queryByRole("button", { name: /^Позиция 1:/ })).not.toBeInTheDocument();
    expect(screen.getByRole("tab", { name: /Сладости/ })).toBeEnabled();
  });

  it("фокус предмета показывает два поворота и сохраняет прямоугольную геометрию", async () => {
    await editor([testSize(11, "tea", 2, 1), testSize(21, "sweet")]); addTea();
    fireEvent.click(screen.getByRole("tab", { name: /Сладости/ }));
    fireEvent.click(screen.getByRole("button", { name: "Добавить Пастила, 50 г" }));
    fireEvent.focus(screen.getByRole("button", { name: /^Позиция 1:/ }));
    const toolbar = screen.getByRole("group", { name: "Поворот: Ассам" });
    fireEvent.click(within(toolbar).getByRole("button", { name: "Повернуть влево на 90°" }));
    expect(screen.getByRole("button", { name: /^Позиция 1:/ })).toHaveAttribute("aria-label", expect.stringContaining("1 × 2 кл."));
    fireEvent.click(within(toolbar).getByRole("button", { name: "Повернуть вправо на 90°" }));
    expect(screen.getByRole("button", { name: /^Позиция 1:/ })).toHaveAttribute("aria-label", expect.stringContaining("2 × 1 кл."));
  });

  it("не поворачивает предмет сквозь другой предмет", async () => {
    await editor([testSize(11, "tea", 2, 1), testSize(21, "sweet")]); addTea();
    fireEvent.click(screen.getByRole("tab", { name: /Сладости/ }));
    fireEvent.click(screen.getByRole("button", { name: "Добавить Пастила, 50 г" }));
    fireEvent.click(screen.getByRole("button", { name: "Ячейка 1, 2" }));
    fireEvent.focus(screen.getByRole("button", { name: /^Позиция 1:/ }));
    fireEvent.click(screen.getByRole("button", { name: "Повернуть вправо на 90°" }));
    expect(screen.getByRole("alert")).toHaveTextContent(/Позиции перекрываются/);
    expect(screen.getByRole("button", { name: /^Позиция 1:/ })).toHaveAttribute("aria-label", expect.stringContaining("2 × 1 кл."));
  });

  it("не поворачивает предмет за границу коробки", async () => {
    await editor([testSize(11, "tea", 2, 1)]); addTea();
    fireEvent.click(screen.getByRole("button", { name: "Ячейка 1, 3" }));
    fireEvent.click(screen.getByRole("button", { name: "Повернуть влево на 90°" }));
    expect(screen.getByRole("alert")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /^Позиция 1:/ })).toHaveAttribute("aria-label", expect.stringContaining("2 × 1 кл."));
  });

  it("уважает can_rotate=false в обе стороны", async () => {
    const size = testSize(11, "tea", 2, 1);
    await editor([{ ...size, size: { ...size.size, can_rotate: false } }]); addTea();
    expect(screen.getByRole("button", { name: "Повернуть влево на 90°" })).toBeDisabled();
    expect(screen.getByRole("button", { name: "Повернуть вправо на 90°" })).toBeDisabled();
    expect(screen.getByRole("button", { name: "Повернуть предмет за ручку" })).toBeDisabled();
  });

  it("рамка и удаление появляются только при выделении; на предмете нет подписей", async () => {
    await editor();
    expect(screen.queryByLabelText("Рамка выделения")).not.toBeInTheDocument();
    addTea();
    const item = screen.getByRole("button", { name: /^Позиция 1:/ });
    expect(item.textContent).toBe("");
    expect(item.querySelector('img[alt=""]')).toBeInTheDocument();
    expect(screen.queryByText(/^Позиция:/)).not.toBeInTheDocument();
    expect(screen.queryByText("Столбец")).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Удалить Ассам из коробки" }));
    expect(screen.queryByRole("button", { name: /^Позиция 1:/ })).not.toBeInTheDocument();
    expect(screen.queryByLabelText("Рамка выделения")).not.toBeInTheDocument();
  });

  it("перемещает стрелками и удаляет Delete без полей координат", async () => {
    await editor(); addTea();
    const item = screen.getByRole("button", { name: /^Позиция 1:/ });
    fireEvent.keyDown(item, { key: "ArrowRight" });
    expect(item).toHaveStyle({ gridColumn: "2 / span 1" });
    fireEvent.keyDown(item, { key: "ArrowDown" });
    expect(item).toHaveStyle({ gridRow: "2 / span 1" });
    fireEvent.keyDown(item, { key: "Delete" });
    expect(screen.queryByRole("button", { name: /^Позиция 1:/ })).not.toBeInTheDocument();
  });

  it("вращает за ручку с привязкой 90°, фиксирует только после отпускания", async () => {
    await editor([testSize(11, "tea", 2, 1)]); addTea();
    const frame = screen.getByLabelText("Рамка выделения");
    vi.spyOn(frame, "getBoundingClientRect").mockReturnValue({ left: 100, top: 100, width: 200, height: 100, right: 300, bottom: 200, x: 100, y: 100, toJSON: () => ({}) });
    fireEvent.pointerDown(screen.getByRole("button", { name: "Повернуть предмет за ручку" }), pointer(200, 80));
    fireEvent.pointerMove(window, pointer(300, 150));
    expect(screen.getByRole("button", { name: /^Позиция 1:/ })).toHaveStyle({ transform: "rotate(90deg)", gridColumn: "1 / span 2" });
    expect(screen.getByRole("button", { name: "Добавить Ассам, 50 г" })).toBeDisabled();
    fireEvent.pointerUp(window, pointer(300, 150));
    expect(screen.getByRole("button", { name: /^Позиция 1:/ })).toHaveStyle({ gridColumn: "1 / span 1", gridRow: "1 / span 2" });
    expect(screen.getByRole("button", { name: "Добавить Ассам, 50 г" })).toBeEnabled();
  });

  it.each(["Escape", "pointercancel", "blur"])("отменяет поворот ручкой: %s", async reason => {
    await editor([testSize(11, "tea", 2, 1)]); addTea();
    vi.spyOn(screen.getByLabelText("Рамка выделения"), "getBoundingClientRect").mockReturnValue({ left: 100, top: 100, width: 200, height: 100, right: 300, bottom: 200, x: 100, y: 100, toJSON: () => ({}) });
    fireEvent.pointerDown(screen.getByRole("button", { name: "Повернуть предмет за ручку" }), pointer(200, 80));
    fireEvent.pointerMove(window, pointer(300, 150));
    if (reason === "Escape") fireEvent.keyDown(window, { key: "Escape" });
    if (reason === "pointercancel") fireEvent.pointerCancel(window, pointer(300, 150));
    if (reason === "blur") fireEvent.blur(window);
    fireEvent.pointerUp(window, pointer(300, 150));
    expect(screen.getByRole("button", { name: /^Позиция 1:/ })).toHaveStyle({ gridColumn: "1 / span 2", gridRow: "1 / span 1" });
    expect(screen.getByRole("button", { name: "Добавить Ассам, 50 г" })).toBeEnabled();
  });

  it("прокручивает страницу при переносе у края и останавливает кадры по Escape", async () => {
    await editor();
    let scrollY = 200;
    vi.spyOn(window, "scrollY", "get").mockImplementation(() => scrollY);
    const scroll = vi.spyOn(window, "scrollBy").mockImplementation((options?: number | ScrollToOptions) => {
      if (typeof options === "object") scrollY += options.top ?? 0;
    });
    const callbacks = new Map<number, FrameRequestCallback>();
    let frame = 0;
    vi.spyOn(window, "requestAnimationFrame").mockImplementation((callback) => { callbacks.set(++frame, callback); return frame; });
    vi.spyOn(window, "cancelAnimationFrame").mockImplementation((id) => { callbacks.delete(id); });
    fireEvent.pointerDown(teaCard(), pointer(650, 150));
    fireEvent.pointerMove(window, pointer(350, 10));
    expect(callbacks.size).toBe(1);
    act(() => { const [id, callback] = [...callbacks][0]; callbacks.delete(id); callback(16); });
    expect(scroll).toHaveBeenCalledOnce();
    expect(scrollY).toBeLessThan(200);
    expect(callbacks.size).toBe(1);
    fireEvent.keyDown(window, { key: "Escape" });
    expect(callbacks.size).toBe(0);
    expect(screen.queryByRole("button", { name: /^Позиция 1:/ })).not.toBeInTheDocument();
  });
});
