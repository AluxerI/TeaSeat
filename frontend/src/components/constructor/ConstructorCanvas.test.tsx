import { act, fireEvent, render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({ invalidate: vi.fn(), dispose: vi.fn(), element: null as HTMLCanvasElement | null }));
vi.mock("@react-three/fiber", () => ({
  Canvas: ({ children, frameloop }: { children: ReactNode; frameloop: string }) => <div data-testid="scene" data-frameloop={frameloop}>{children}</div>,
  useThree: () => ({ gl: { domElement: mocks.element }, invalidate: mocks.invalidate }),
}));
vi.mock("./three/materials", () => ({ disposeConstructorTextures: mocks.dispose }));
import ConstructorCanvas from "./ConstructorCanvas";

beforeEach(() => {
  mocks.element = document.createElement("canvas");
  mocks.invalidate.mockClear(); mocks.dispose.mockClear();
  Object.defineProperty(document, "visibilityState", { configurable: true, value: "visible" });
});

describe("constructor canvas lifecycle without renderer", () => {
  it("останавливает кадры в скрытой вкладке и возобновляет по invalidate", () => {
    render(<ConstructorCanvas fallback={<p>Нет 3D</p>} />);
    expect(screen.getByTestId("scene")).toHaveAttribute("data-frameloop", "demand");
    act(() => { Object.defineProperty(document, "visibilityState", { configurable: true, value: "hidden" }); document.dispatchEvent(new Event("visibilitychange")); });
    expect(screen.getByTestId("scene")).toHaveAttribute("data-frameloop", "never");
    mocks.invalidate.mockClear();
    act(() => { Object.defineProperty(document, "visibilityState", { configurable: true, value: "visible" }); document.dispatchEvent(new Event("visibilitychange")); });
    expect(screen.getByTestId("scene")).toHaveAttribute("data-frameloop", "demand");
    expect(mocks.invalidate).toHaveBeenCalled();
  });

  it("при потере WebGL оставляет доступный fallback", () => {
    render(<ConstructorCanvas fallback={<p role="status">Выберите размер ниже</p>} />);
    fireEvent(mocks.element!, new Event("webglcontextlost", { cancelable: true }));
    expect(screen.getByRole("status")).toHaveTextContent("Выберите размер ниже");
    expect(screen.queryByTestId("scene")).not.toBeInTheDocument();
  });

  it("не освобождает общий кеш, пока другая сцена использует его", async () => {
    const first = render(<ConstructorCanvas fallback={null} />);
    const second = render(<ConstructorCanvas fallback={null} />);
    await act(async () => { first.unmount(); });
    expect(mocks.dispose).not.toHaveBeenCalled();
    await act(async () => { second.unmount(); });
    expect(mocks.dispose).toHaveBeenCalledOnce();
  });
});
