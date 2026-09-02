import { act, cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { packingBox, packingDuration, packingKeys, packingSlot } from "../../utils/simplePacking";
import { testBox, testQuote, testSizes } from "./testFixtures";
import { verifiedSimpleQuote } from "../../utils/simpleGiftValidation";

// jsdom не объявляет AnimationEvent; React иначе выбирает webkitAnimationEnd.
vi.hoisted(() => { if (!("AnimationEvent" in window)) Object.defineProperty(window, "AnimationEvent", { configurable: true, value: Event }); });

// Если в 2D вернётся зависимость от WebGL, этот тест упадёт уже при импорте.
vi.mock("@react-three/fiber", () => { throw new Error("2D packing must not import WebGL"); });
import SimplePackingScene from "./SimplePackingScene";

const listeners = new Set<() => void>();
const media = { matches: false, addEventListener: (_name: string, change: () => void) => listeners.add(change),
  removeEventListener: (_name: string, change: () => void) => listeners.delete(change) };
const actor = (key: string) => document.querySelector<SVGGElement>('[data-packing-key="' + key + '"]')!;
const state = (key: string) => actor(key).getAttribute("data-state");
const advance = (ms: number) => act(() => { vi.advanceTimersByTime(ms); });
const visibility = (value: "visible" | "hidden") => act(() => {
  Object.defineProperty(document, "visibilityState", { configurable: true, value });
  document.dispatchEvent(new Event("visibilitychange"));
});

beforeEach(() => {
  vi.useFakeTimers();
  listeners.clear(); media.matches = false;
  vi.stubGlobal("matchMedia", vi.fn(() => media));
  vi.stubGlobal("requestAnimationFrame", vi.fn());
  Object.defineProperty(document, "visibilityState", { configurable: true, value: "visible" });
});
afterEach(() => { cleanup(); vi.clearAllTimers(); vi.useRealTimers(); vi.unstubAllGlobals(); });

describe("2D packing and queue lifecycle", () => {
  it("показывает SVG-коробку без WebGL и не запускает пустую очередь", () => {
    render(<SimplePackingScene box={testBox} teas={[]} sweets={[]} seen={{ current: new Set() }} />);
    expect(screen.getByRole("img", { name: "2D-упаковка: " + testBox.name })).toBeInTheDocument();
    expect(document.querySelector("canvas")).toBeNull();
    expect(vi.getTimerCount()).toBe(0);
    expect(window.requestAnimationFrame).not.toHaveBeenCalled();
  });

  it("упаковывает 5 чаёв, затем 2 сладости, без наложения анимаций", () => {
    const seen = { current: new Set<string>() };
    const teas = [11, 11, 11, 11, 11], sweets = [21, 21];
    render(<SimplePackingScene box={testBox} teas={teas} sweets={sweets} seen={seen} />);
    const entries = packingKeys(teas, sweets);
    expect(document.querySelectorAll("[data-packing-key]")).toHaveLength(7);
    for (const entry of entries) {
      expect(document.querySelectorAll('[data-state="active"]')).toHaveLength(1);
      expect(state(entry.key)).toBe("active");
      fireEvent.animationEnd(actor(entry.key));
      expect(state(entry.key)).toBe("packed");
    }
    expect(seen.current.size).toBe(7);
    expect(document.querySelectorAll('[data-state="queued"]')).toHaveLength(0);
    expect(vi.getTimerCount()).toBe(0);
  });

  it("не принимает animationend от вложенного клапана за завершение упаковки", () => {
    const seen = { current: new Set<string>() };
    render(<SimplePackingScene box={testBox} teas={[11]} sweets={[21]} seen={seen} />);
    fireEvent.animationEnd(actor("tea:11:0").querySelector("path")!);
    expect(state("tea:11:0")).toBe("active");
    expect(seen.current.size).toBe(0);
    fireEvent.animationEnd(actor("tea:11:0"));
    fireEvent.animationEnd(actor("tea:11:0"));
    expect(state("sweet:21:0")).toBe("active");
    expect(seen.current.size).toBe(1);
  });

  it("при отсутствии animationend завершает такт одним резервным таймером", () => {
    const seen = { current: new Set<string>() };
    render(<SimplePackingScene box={testBox} teas={[11]} sweets={[]} seen={seen} />);
    expect(vi.getTimerCount()).toBe(1);
    advance(packingDuration("tea") * 1000 + 80);
    expect(state("tea:11:0")).toBe("packed");
    expect(vi.getTimerCount()).toBe(0);
  });

  it("на скрытой вкладке приостанавливает CSS и отсчёт, затем продолжает остаток", () => {
    render(<SimplePackingScene box={testBox} teas={[11]} sweets={[]} seen={{ current: new Set() }} />);
    advance(1000);
    visibility("hidden");
    expect(screen.getByLabelText("Анимация упаковки подарка")).toHaveAttribute("data-paused", "true");
    expect(vi.getTimerCount()).toBe(0);
    advance(10000);
    expect(state("tea:11:0")).toBe("active");
    visibility("visible");
    advance(1500);
    expect(state("tea:11:0")).toBe("active");
    advance(1500);
    expect(state("tea:11:0")).toBe("packed");
  });

  it("не начинает очередь до открытия скрытой вкладки", () => {
    visibility("hidden");
    render(<SimplePackingScene box={testBox} teas={[11]} sweets={[]} seen={{ current: new Set() }} />);
    expect(vi.getTimerCount()).toBe(0);
    visibility("visible");
    expect(vi.getTimerCount()).toBe(1);
  });

  it("удаление активной позиции отменяет старый такт", () => {
    const seen = { current: new Set<string>() };
    const view = render(<SimplePackingScene box={testBox} teas={[11, 12]} sweets={[]} seen={seen} />);
    advance(1000);
    view.rerender(<SimplePackingScene box={testBox} teas={[12]} sweets={[]} seen={seen} />);
    expect(actor("tea:11:0")).toBeNull();
    expect(state("tea:12:0")).toBe("active");
    advance(packingDuration("tea") * 1000 + 80);
    expect([...seen.current]).toEqual(["tea:12:0"]);
  });

  it("новый выбор не перезапускает текущую анимацию", () => {
    const seen = { current: new Set<string>() };
    const view = render(<SimplePackingScene box={testBox} teas={[]} sweets={[21]} seen={seen} />);
    advance(1000);
    view.rerender(<SimplePackingScene box={testBox} teas={[11]} sweets={[21]} seen={seen} />);
    expect(state("sweet:21:0")).toBe("active");
    advance(packingDuration("sweet") * 1000 + 80 - 1000);
    expect(state("sweet:21:0")).toBe("packed");
    expect(state("tea:11:0")).toBe("active");
  });

  it("reduced motion сразу показывает наполнение без таймеров", () => {
    media.matches = true;
    render(<SimplePackingScene box={testBox} teas={[11, 11]} sweets={[21]} seen={{ current: new Set() }} />);
    expect(document.querySelectorAll('[data-state="packed"]')).toHaveLength(3);
    expect(vi.getTimerCount()).toBe(0);
  });

  it("изменение reduced motion во время работы завершает очередь", () => {
    render(<SimplePackingScene box={testBox} teas={[11]} sweets={[21]} seen={{ current: new Set() }} />);
    act(() => { media.matches = true; listeners.forEach((listener) => listener()); });
    expect(document.querySelectorAll('[data-state="packed"]')).toHaveLength(2);
    expect(vi.getTimerCount()).toBe(0);
  });

  it("возврат не переигрывает упакованное; повторное добавление после удаления анимируется", () => {
    const seen = { current: new Set<string>() };
    const view = render(<SimplePackingScene box={testBox} teas={[11]} sweets={[]} seen={seen} />);
    fireEvent.animationEnd(actor("tea:11:0"));
    view.unmount();
    const next = render(<SimplePackingScene box={testBox} teas={[11]} sweets={[]} seen={seen} />);
    expect(state("tea:11:0")).toBe("packed");
    expect(vi.getTimerCount()).toBe(0);
    next.rerender(<SimplePackingScene box={testBox} teas={[]} sweets={[]} seen={seen} />);
    next.rerender(<SimplePackingScene box={testBox} teas={[11]} sweets={[]} seen={seen} />);
    expect(state("tea:11:0")).toBe("active");
  });

  it("уход со страницы очищает таймер и подписку", () => {
    const view = render(<SimplePackingScene box={testBox} teas={[11]} sweets={[21]} seen={{ current: new Set() }} />);
    expect(listeners.size).toBe(1);
    view.unmount();
    expect(vi.getTimerCount()).toBe(0);
    expect(listeners.size).toBe(0);
  });

  it("не интерпретирует имя коробки как HTML", () => {
    const name = '<img src=x onerror="alert(1)">';
    render(<SimplePackingScene box={{ ...testBox, name }} teas={[]} sweets={[]} seen={{ current: new Set() }} />);
    expect(screen.getByRole("img", { name: "2D-упаковка: " + name })).toBeInTheDocument();
    expect(document.querySelector("img, script")).toBeNull();
  });

  it.each([3, 7, 16, 40])("распределяет %s декоративных слотов внутри коробки", (count) => {
    const slots = Array.from({ length: count }, (_, index) => packingSlot(index, count, 2.4, 1.8));
    expect(new Set(slots.map((item) => item.x + ":" + item.z)).size).toBe(count);
    for (const item of slots) {
      expect(Math.abs(item.x) + .42 * item.scale).toBeLessThanOrEqual(1.2);
      expect(Math.abs(item.z) + .42 * item.scale).toBeLessThanOrEqual(.9);
    }
  });

  it("сохраняет пропорции коробки и стабильные ключи повторений", () => {
    const bounds = packingBox(4, 3);
    expect(bounds.width / bounds.height).toBeCloseTo(4 / 3);
    expect(bounds.y + bounds.height + 14).toBeLessThan(410);
    const keys = packingKeys([11, 11, 11], [21]);
    expect(new Set(keys.map((item) => item.key)).size).toBe(4);
    expect(keys[1].key).toBe(packingKeys([11, 11], [21])[1].key);
  });

  it("после проверки использует координаты и занимаемое место из backend", () => {
    const teas = [11, 11, 11, 11, 11], sweets = [21, 21];
    const { layout } = verifiedSimpleQuote(testQuote, testBox, testSizes, { box_profile_id: 4, tea_product_size_ids: teas, sweet_product_size_ids: sweets });
    render(<SimplePackingScene box={testBox} teas={teas} sweets={sweets} seen={{ current: new Set() }} layout={layout} />);
    expect(screen.getByLabelText("Анимация упаковки подарка")).toHaveAttribute("data-layout", "server");
    const bounds = packingBox(4, 3), unit = bounds.width / 4;
    expect(document.querySelectorAll("[data-footprint]")).toHaveLength(7);
    expect(actor("tea:11:4").style.getPropertyValue("--pack-x")).toBe((bounds.x + unit / 2) + "px");
    expect(actor("tea:11:4").style.getPropertyValue("--pack-y")).toBe((bounds.y + unit * 1.5) + "px");
    expect(document.querySelector('[data-footprint="test-position-4"]')).toHaveAttribute("width", String(unit - 2));
  });

  it("поворот визуализации не меняет уже повёрнутые стороны раскладки", () => {
    render(<SimplePackingScene box={testBox} teas={[11]} sweets={[]} seen={{ current: new Set() }} layout={[
      { client_item_id: "rotated", product_size_id: 11, position_x: 0, position_y: 0, width_cells: 1, height_cells: 2, is_rotated: true },
    ]} />);
    const rect = document.querySelector('[data-footprint="rotated"]')!;
    const unit = packingBox(4, 3).width / 4;
    expect(rect).toHaveAttribute("width", String(unit - 2));
    expect(rect).toHaveAttribute("height", String(unit * 2 - 2));
    expect(actor("tea:11:0").firstElementChild).toHaveAttribute("transform", "rotate(90)");
  });

  it("мелкий след на большой коробке не получает отрицательные SVG-размеры", () => {
    render(<SimplePackingScene box={{ ...testBox, width_cells: 4000, height_cells: 3000 }} teas={[11]} sweets={[]} seen={{ current: new Set() }} layout={[
      { client_item_id: "tiny", product_size_id: 11, position_x: 0, position_y: 0, width_cells: 1, height_cells: 1, is_rotated: false },
    ]} />);
    const rect = document.querySelector('[data-footprint="tiny"]')!;
    expect(Number(rect.getAttribute("width"))).toBeGreaterThan(0);
    expect(Number(rect.getAttribute("height"))).toBeGreaterThan(0);
  });
});
