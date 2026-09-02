import { fireEvent, render, screen } from "@testing-library/react";
import type { ReactNode } from "react";
import { PerspectiveCamera } from "three";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { testBox } from "./testFixtures";
import type { SceneChoice } from "./ConstructorSceneChoice";

const mocks = vi.hoisted(() => ({ state: {} as { camera?: unknown; size?: { width: number; height: number }; pointer?: { x: number; y: number }; invalidate?: () => void }, invalidate: vi.fn(), frame: vi.fn() }));
vi.mock("@react-three/fiber", () => ({ useThree: () => mocks.state, useFrame: mocks.frame }));
vi.mock("@react-three/drei", () => ({ Html: () => null, Environment: () => null, Lightformer: () => null }));
vi.mock("./ConstructorCanvas", () => ({ default: ({ fallback }: { fallback: ReactNode }) => fallback }));
vi.mock("./three/GiftBox", () => ({ default: () => null, createBoxDrive: vi.fn() }));
import ConstructorBoxScene, { SelectionCamera } from "./ConstructorBoxScene";

beforeEach(() => {
  mocks.invalidate.mockClear(); mocks.frame.mockClear();
  mocks.state = { camera: new PerspectiveCamera(38, 1000 / 448), size: { width: 1000, height: 448 }, pointer: { x: 0, y: 0 }, invalidate: mocks.invalidate };
});

describe("static selection scene", () => {
  it("камера не имеет кадрового цикла и не реагирует поворотом на курсор", () => {
    const camera = mocks.state.camera as PerspectiveCamera;
    const view = render(<SelectionCamera columns={2} rows={1} />);
    const position = camera.position.clone(), rotation = camera.quaternion.clone();
    mocks.invalidate.mockClear();
    mocks.state.pointer = { x: 1, y: -1 };
    view.rerender(<SelectionCamera columns={2} rows={1} />);
    expect(camera.position.equals(position)).toBe(true);
    expect(camera.quaternion.equals(rotation)).toBe(true);
    expect(mocks.invalidate).not.toHaveBeenCalled();
    expect(mocks.frame).not.toHaveBeenCalled();
  });

  it("при узком экране вмещает вертикальный выбор и не создаёт автокручение", () => {
    const camera = mocks.state.camera as PerspectiveCamera;
    const view = render(<SelectionCamera columns={2} rows={1} />);
    const desktopDistance = camera.position.length();
    mocks.state.size = { width: 350, height: 589 };
    camera.aspect = 350 / 589;
    view.rerender(<SelectionCamera columns={1} rows={2} />);
    expect(camera.position.length()).toBeGreaterThan(desktopDistance);
    expect(camera.position.x).toBe(0);
    expect(mocks.frame).not.toHaveBeenCalled();
  });

  it("без WebGL обе реальные коробки доступны одним набором кнопок", () => {
    const select = vi.fn();
    const choices: SceneChoice[] = [testBox, { ...testBox, id: 5, name: "Малая коробка" }].map((box) => ({
      id: String(box.id), box, kind: "box", title: box.name, caption: "4 × 3 клетки", ariaLabel: "Выбрать " + box.name, onSelect: () => select(box.id),
    }));
    render(<ConstructorBoxScene choices={choices} label="Выбор коробок" />);
    expect(screen.getAllByRole("button")).toHaveLength(2);
    fireEvent.click(screen.getByRole("button", { name: "Выбрать Малая коробка" }));
    expect(select).toHaveBeenCalledExactlyOnceWith(5);
  });

  it("не включает запрещённый способ и не интерпретирует имя как HTML", () => {
    const onSelect = vi.fn(), title = '<img src=x onerror="alert(1)">';
    render(<ConstructorBoxScene label="Способы" choices={[{
      id: "simple", box: testBox, kind: "simple", title, caption: "Только ручная сборка", ariaLabel: "Быстрая сборка", disabled: true, onSelect,
    }]} />);
    const button = screen.getByRole("button", { name: "Быстрая сборка" });
    expect(button).toBeDisabled();
    expect(button).toHaveTextContent(title);
    fireEvent.click(button);
    expect(onSelect).not.toHaveBeenCalled();
    expect(document.querySelector("img, script")).toBeNull();
  });
});
