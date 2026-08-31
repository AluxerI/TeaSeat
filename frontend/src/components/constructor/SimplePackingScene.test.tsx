import { act, render } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { SachetDrive } from "./three/TeaSachet";
import type { SweetDrive } from "./three/SweetItem";
import type { PourDrive } from "./three/LeafParticles";
import { packingKeys, packingProgress, packingSlot } from "../../utils/simplePacking";

const mocks = vi.hoisted(() => ({
  frame: null as ((state: unknown, delta: number) => void) | null, invalidate: vi.fn(),
  tea: null as { current: SachetDrive } | null, sweet: null as { current: SweetDrive } | null,
  pour: null as { current: PourDrive } | null,
}));
vi.mock("@react-three/fiber", () => ({ Canvas: () => null, useThree: () => ({ invalidate: mocks.invalidate }),
  useFrame: (callback: typeof mocks.frame) => { mocks.frame = callback; } }));
vi.mock("@react-three/drei", () => ({ RoundedBox: () => null }));
vi.mock("./three/TeaSachet", async (actual) => ({ ...await actual<object>(), default: ({ drive }: { drive: { current: SachetDrive } }) => { mocks.tea = drive; return null; } }));
vi.mock("./three/SweetItem", async (actual) => ({ ...await actual<object>(), default: ({ drive }: { drive: { current: SweetDrive } }) => { mocks.sweet = drive; return null; } }));
vi.mock("./three/LeafParticles", async (actual) => ({ ...await actual<object>(), default: ({ drive }: { drive: { current: PourDrive } }) => { mocks.pour = drive; return null; } }));
import { PackedItem } from "./SimplePackingScene";

const slot = { x: .5, z: -.4, scale: .7 };
const frames = (count: number) => act(() => { for (let index = 0; index < count; index += 1) mocks.frame?.({}, .05); });
beforeEach(() => {
  mocks.invalidate.mockClear(); mocks.frame = null; mocks.tea = null; mocks.sweet = null; mocks.pour = null;
  vi.stubGlobal("matchMedia", vi.fn().mockReturnValue({ matches: false }));
});
afterEach(() => vi.unstubAllGlobals());

describe("packing animation controllers, without WebGL", () => {
  it("открывает чай, наполняет и укладывает, затем прекращает перерисовки", () => {
    render(<PackedItem role="tea" slot={slot} itemKey="tea:11:0" seen={{ current: new Set() }} />);
    frames(34);
    expect(mocks.tea!.current.flap).toBe(1);
    expect(mocks.tea!.current.fill).toBeGreaterThan(0);
    expect(mocks.tea!.current.fill).toBeLessThan(1);
    expect(mocks.pour!.current.active).toBe(true);
    frames(50);
    expect(mocks.tea!.current.position.x).toBe(slot.x);
    expect(mocks.tea!.current.position.z).toBeCloseTo(slot.z);
    expect(mocks.tea!.current.rotation.x).toBe(-Math.PI / 2);
    expect(mocks.tea!.current.fill).toBe(1);
    expect(mocks.tea!.current.flap).toBe(0);
    expect(mocks.pour!.current.active).toBe(false);
    mocks.invalidate.mockClear(); frames(10);
    expect(mocks.invalidate).not.toHaveBeenCalled();
  });

  it("заворачивает сладость и завязывает ленту без изменения состава", () => {
    render(<PackedItem role="sweet" slot={slot} itemKey="sweet:21:0" seen={{ current: new Set() }} />);
    frames(18);
    expect(mocks.sweet!.current.wrap).toBeGreaterThan(0);
    expect(mocks.sweet!.current.wrap).toBeLessThan(1);
    frames(40);
    expect(mocks.sweet!.current.wrap).toBe(1);
    expect(mocks.sweet!.current.ribbon).toBe(1);
    expect(mocks.sweet!.current.position.z).toBeCloseTo(slot.z);
    expect(mocks.sweet!.current.rotation.toArray().slice(0, 3)).toEqual([0, 0, 0]);
  });

  it("ожидает своей очереди без кадров, затем один раз сообщает о завершении", () => {
    const completed = vi.fn();
    const seen = { current: new Set<string>() };
    const view = render(<PackedItem role="tea" slot={slot} itemKey="tea:11:0" seen={seen} pending onComplete={completed} />);
    mocks.invalidate.mockClear(); frames(80);
    expect(mocks.tea!.current.visible).toBe(false);
    expect(mocks.invalidate).not.toHaveBeenCalled();
    expect(completed).not.toHaveBeenCalled();
    view.rerender(<PackedItem role="tea" slot={slot} itemKey="tea:11:0" seen={seen} onComplete={completed} />);
    frames(100);
    expect(mocks.tea!.current.visible).toBe(true);
    expect(completed).toHaveBeenCalledOnce();
    expect(seen.current.has("tea:11:0")).toBe(true);
  });

  it.each(["seen", "reduced"])("не повторяет движение: %s", (reason) => {
    vi.mocked(window.matchMedia).mockReturnValue({ matches: reason === "reduced" } as MediaQueryList);
    render(<PackedItem role="tea" slot={slot} itemKey="tea:11:0" seen={{ current: new Set(reason === "seen" ? ["tea:11:0"] : []) }} />);
    mocks.invalidate.mockClear(); frames(1);
    expect(mocks.tea!.current.position.x).toBe(slot.x);
    expect(mocks.pour!.current.active).toBe(false);
    expect(mocks.invalidate).not.toHaveBeenCalled();
  });

  it("сохраняет ключи повторяющихся форматов и поддерживает набор 5+2", () => {
    const entries = packingKeys([11, 11, 11, 11, 11], [21, 21]);
    expect(entries).toHaveLength(7);
    expect(new Set(entries.map((item) => item.key)).size).toBe(7);
    expect(entries.filter((item) => item.role === "tea")).toHaveLength(5);
    expect(packingKeys([11, 11], [21])[1].key).toBe(entries[1].key);
  });

  it.each([3, 7, 16, 40])("распределяет %s декоративных слотов внутри коробки", (count) => {
    const slots = Array.from({ length: count }, (_, index) => packingSlot(index, count, 2.4, 1.8));
    expect(new Set(slots.map((item) => `${item.x}:${item.z}`)).size).toBe(count);
    for (const item of slots) {
      expect(Math.abs(item.x) + .42 * item.scale).toBeLessThanOrEqual(1.2);
      expect(Math.abs(item.z) + .42 * item.scale).toBeLessThanOrEqual(.9);
      expect(item.scale).toBeGreaterThan(0);
    }
    expect(packingProgress(-1, 0, .5)).toBe(0);
    expect(packingProgress(99, 0, .5)).toBe(1);
  });
});
