import { act, render } from "@testing-library/react";
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
import { FloorCamera } from "./BoxFloorScene";

beforeEach(() => {
  mocks.invalidate.mockClear();
  mocks.frame = null;
  mocks.state = { camera: new OrthographicCamera(), size: { width: 600, height: 350 }, invalidate: mocks.invalidate };
  vi.stubGlobal("matchMedia", vi.fn().mockReturnValue({ matches: false } as MediaQueryList));
});
afterEach(() => vi.unstubAllGlobals());
const tick = () => act(() => { mocks.frame?.({}, .05); });

describe("camera controller without WebGL", () => {
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

  it("возвращается сразу к дну после повторного подключения Canvas", () => {
    const camera = mocks.state.camera as OrthographicCamera;
    render(<FloorCamera width={4} height={3} editing />);
    mocks.invalidate.mockClear();
    tick();
    expect(camera.position.toArray()).toEqual([0, 8, 0]);
    expect(mocks.invalidate).not.toHaveBeenCalled();
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
});
