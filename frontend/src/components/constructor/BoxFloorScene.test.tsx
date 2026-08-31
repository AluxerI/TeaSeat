import { act, fireEvent, render, screen } from "@testing-library/react";
import { OrthographicCamera } from "three";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({
  state: {} as { camera?: unknown; size?: { width: number; height: number }; invalidate?: () => void },
  frame: null as ((state: unknown, delta: number) => void) | null,
  invalidate: vi.fn(),
}));
vi.mock("@react-three/fiber", () => ({
  Canvas: () => null,
  useThree: () => mocks.state,
  useFrame: (callback: typeof mocks.frame) => { mocks.frame = callback; },
}));
vi.mock("@react-three/drei", () => ({ Html: () => null }));
vi.mock("./three/GiftBox", () => ({ default: () => null, createBoxDrive: vi.fn() }));
vi.mock("./three/materials", () => ({ disposeConstructorTextures: vi.fn() }));
import BoxFloorScene, { FloorCamera, FLOOR_ITEM_Y, FLOOR_ITEM_TOP, FLOOR_WALL_HEIGHT } from "./BoxFloorScene";
import { testBox, testSizes } from "./testFixtures";

beforeEach(() => {
  mocks.invalidate.mockClear();
  mocks.frame = null;
  mocks.state = { camera: new OrthographicCamera(), size: { width: 600, height: 350 }, invalidate: mocks.invalidate };
  vi.stubGlobal("matchMedia", vi.fn().mockReturnValue({ matches: false } as MediaQueryList));
});
afterEach(() => vi.unstubAllGlobals());
const tick = () => act(() => { mocks.frame?.({}, .05); });

describe("camera controller without WebGL", () => {
  it("в ручном режиме нет обзора и поворота камеры; fallback сохраняет сетку", () => {
    render(<BoxFloorScene box={testBox} sizes={testSizes} items={[]} selectedId={null} editing onChoose={vi.fn()} onSelect={vi.fn()} onCell={vi.fn()} />);
    expect(screen.queryByRole("button", { name: "Обзор" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Сверху" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /Повернуть коробку/ })).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Без 3D" }));
    expect(screen.getByLabelText("Дно коробки, вид сверху")).toBeInTheDocument();
    expect(screen.getAllByRole("button", { name: /^Ячейка/ })).toHaveLength(12);
  });

  it("бортик слегка выше предметов, а дно остаётся на прежней плоскости", () => {
    expect(FLOOR_ITEM_Y).toBe(.15);
    expect(FLOOR_WALL_HEIGHT - FLOOR_ITEM_TOP).toBeCloseTo(.08);
    expect(FLOOR_WALL_HEIGHT).toBeLessThan(.4);
    // GiftBox сдвинут на BH/2 до масштаба Y: низ не поднимается вместе с бортиком.
    expect((-1.2 / 2 + 1.2 / 2) * FLOOR_WALL_HEIGHT / 1.2).toBe(0);
  });

  it("не запускает движение при загрузке коробки, переходит после выбора и останавливает invalidation", () => {
    const camera = mocks.state.camera as OrthographicCamera;
    const view = render(<FloorCamera width={4} height={3} editing={false} />);
    mocks.invalidate.mockClear();
    tick();
    const preview = camera.position.clone();
    tick();
    expect(camera.position.equals(preview)).toBe(true);
    expect(mocks.invalidate).not.toHaveBeenCalled();

    view.rerender(<FloorCamera width={4} height={3} editing />);
    expect(mocks.invalidate).toHaveBeenCalled();
    tick();
    expect(camera.position.z).toBeLessThan(preview.z);
    for (let index = 0; index < 20; index += 1) tick();
    expect(camera.position.toArray()).toEqual([0, 8, 0]);
    mocks.invalidate.mockClear();
    tick();
    expect(mocks.invalidate).not.toHaveBeenCalled();
  });

  it("начинает переход к дну сразу после выбора ручного режима", () => {
    const camera = mocks.state.camera as OrthographicCamera;
    render(<FloorCamera width={4} height={3} editing />);
    mocks.invalidate.mockClear();
    tick();
    expect(camera.position.z).toBeGreaterThan(0);
    expect(mocks.invalidate).toHaveBeenCalled();
    for (let index = 0; index < 20; index += 1) tick();
    expect(camera.position.toArray()).toEqual([0, 8, 0]);
  });

  it("с reduced motion ждёт подтверждения и затем пропускает анимацию", () => {
    vi.mocked(window.matchMedia).mockReturnValue({ matches: true } as MediaQueryList);
    const camera = mocks.state.camera as OrthographicCamera;
    const view = render(<FloorCamera width={4} height={3} editing={false} />);
    tick();
    expect(camera.position.z).toBeGreaterThan(0);
    view.rerender(<FloorCamera width={4} height={3} editing />);
    tick();
    expect(camera.position.toArray()).toEqual([0, 8, 0]);
  });

  it("ограничивает ручной ракурс и возвращает его к дну до разрешения переноса", () => {
    const camera = mocks.state.camera as OrthographicCamera;
    const settled = vi.fn();
    const view = render(<FloorCamera width={4} height={3} editing={false} previewRotation={100} onSettled={settled} />);
    tick();
    expect(camera.position.x).toBeGreaterThan(0);
    expect(Math.atan2(camera.position.x, camera.position.z)).toBeCloseTo(Math.PI / 5);
    expect(settled).not.toHaveBeenCalledWith(true);
    view.rerender(<FloorCamera width={4} height={3} editing previewRotation={100} onSettled={settled} />);
    for (let index = 0; index < 20; index += 1) tick();
    expect(camera.position.toArray()).toEqual([0, 8, 0]);
    expect(settled).toHaveBeenCalledWith(true);
  });
});
