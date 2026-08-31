import { describe, expect, it } from "vitest";
import { createBowGeometry } from "./three/bowGeometry";
import { clampPreviewRotation, dragScrollDelta, floorPoint, floorViewport, formatFootprint } from "../../utils/constructorInteraction";
import { testBox } from "./testFixtures";

describe("constructor geometry and dimensions", () => {
  it("автоскролл работает только у краёв, ограничивает скорость и учитывает FPS", () => {
    expect(dragScrollDelta(300, 600, 16)).toBe(0);
    expect(dragScrollDelta(10, 600, 16)).toBeLessThan(0);
    expect(dragScrollDelta(590, 600, 16)).toBeGreaterThan(0);
    expect(dragScrollDelta(600, 600, 32)).toBeCloseTo(dragScrollDelta(600, 600, 16) * 2);
    expect(dragScrollDelta(900, 600, 5000)).toBe(15.36);
    expect(dragScrollDelta(10, 0, 16)).toBe(0);
  });
  it("строит ограниченную геометрию банта без NaN и некорректных индексов", () => {
    const { loop, tail } = createBowGeometry();
    for (const geometry of [loop, tail]) {
      const position = geometry.getAttribute("position");
      expect(position.count).toBeLessThan(300);
      expect([...position.array].every(Number.isFinite)).toBe(true);
      expect([...geometry.getAttribute("normal").array].every(Number.isFinite)).toBe(true);
      expect([...geometry.getIndex()!.array].every((index) => index >= 0 && index < position.count)).toBe(true);
      expect(geometry.boundingSphere!.radius).toBeGreaterThan(0);
      geometry.dispose();
    }
  });

  it("одинаково масштабирует камеру и слой ячеек", () => {
    const surface = floorViewport(600, 350, 4, 3);
    expect(surface.width / surface.height).toBeCloseTo(4 / 3);
    expect(surface.width / 4).toBeCloseTo(surface.zoom);
    expect(surface.height).toBeLessThan(350);
    expect(floorPoint(300, 200, { left: 100, top: 50, width: 400, height: 300 }, testBox)).toEqual({ x: 2, y: 1.5 });
  });

  it("показывает миллиметры только из явного размера ячейки", () => {
    expect(formatFootprint(2, 3, 10)).toBe("2 × 3 кл. · 20 × 30 мм");
    expect(formatFootprint(2, 3, null)).toBe("2 × 3 кл.");
    expect(formatFootprint(2, 3, NaN)).toBe("2 × 3 кл.");
    expect(clampPreviewRotation(50)).toBe(Math.PI / 5);
    expect(clampPreviewRotation(-50)).toBe(-Math.PI / 5);
  });
});
