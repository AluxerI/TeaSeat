import { describe, expect, it } from "vitest";
import { testBox, testSize } from "../components/constructor/testFixtures";
import { firstFreePlacement, footprint, layoutError, MAX_LAYOUT_ITEMS } from "./giftLayout";
import { advanceFloorCamera, floorCameraPosition } from "./floorCamera";

const sizes = [testSize(11, "tea", 2, 1)];
const item = { client_item_id: "first", product_size_id: 11, position_x: 0, position_y: 0, is_rotated: false };

describe("gift floor layout", () => {
  it("учитывает поворот прямоугольного формата", () => expect(footprint(sizes[0], true)).toEqual([1, 2]));
  it("размещает одинаковый товар отдельными неперекрывающимися экземплярами", () => {
    const next = firstFreePlacement(testBox, sizes, [item], sizes[0], "second");
    expect(next).toMatchObject({ position_x: 2, position_y: 0 });
    expect(layoutError(testBox, sizes, [item, next!])).toBeNull();
  });
  it.each([{ position_x: -1 }, { position_x: .5 }, { position_x: 3 }, { position_y: 3 }])("блокирует выход за границы: %s", (changes) => {
    expect(layoutError(testBox, sizes, [{ ...item, ...changes }])).toMatch(/границы/);
  });
  it("не допускает перекрытия после поворота", () => {
    const next = { ...item, client_item_id: "second", position_y: 1 };
    expect(layoutError(testBox, sizes, [{ ...item, is_rotated: true }, next])).toMatch(/перекрываются/);
  });
  it("запрещает поворот и неизвестные форматы", () => {
    expect(layoutError(testBox, [{ ...sizes[0], size: { ...sizes[0].size, can_rotate: false } }], [{ ...item, is_rotated: true }])).toMatch(/нельзя/);
    expect(layoutError(testBox, [], [item])).toMatch(/недоступен/);
  });
  it("сообщает, когда нет места", () => {
    expect(firstFreePlacement({ ...testBox, width_cells: 1, height_cells: 1 }, sizes, [], sizes[0], "new")).toBeNull();
  });
  it("ограничивает число позиций", () => {
    expect(layoutError(testBox, sizes, Array(MAX_LAYOUT_ITEMS + 1).fill(item))).toMatch(/Максимум/);
  });
  it("работает с большой коробкой без перебора миллионов ячеек", () => {
    const huge = testSize(12, "tea", 1000000, 1000000);
    const box = { ...testBox, width_cells: 2000000, height_cells: 1000000 };
    const first = { ...item, product_size_id: huge.id };
    expect(layoutError(box, [huge], [first])).toBeNull();
    expect(firstFreePlacement(box, [huge], [first], huge, "next")).toMatchObject({ position_x: 1000000, position_y: 0 });
  });
  it("ищет свободное место по краям уже размещённых предметов", () => {
    const blockers = [{ ...item, position_x: 1 }, { ...item, client_item_id: "second", position_x: 0, position_y: 1 }];
    expect(firstFreePlacement(testBox, sizes, blockers, sizes[0], "next")).toMatchObject({ position_x: 2, position_y: 1 });
  });
});

describe("fixed floor camera", () => {
  it("держит прежний ракурс до подтверждения коробки, а потом переходит ко дну", () => {
    expect(advanceFloorCamera(0, 10, false, false)).toBe(0);
    expect(advanceFloorCamera(0, .04, true, false)).toBeGreaterThan(0);
    expect(advanceFloorCamera(1, .04, true, false)).toBe(1);
    expect(advanceFloorCamera(.5, .04, false, false)).toBe(0);
  });
  it("reduced motion не обходит выбор коробки, но убирает движение после выбора", () => {
    expect(advanceFloorCamera(0, .04, false, true)).toBe(0);
    expect(advanceFloorCamera(0, .04, true, true)).toBe(1);
  });
  it("переходит от наклонного вида строго к дну", () => {
    expect(floorCameraPosition(0, 4, 3)[2]).toBeGreaterThan(0);
    expect(floorCameraPosition(.5, 4, 3)[2]).toBeLessThan(floorCameraPosition(0, 4, 3)[2]);
    expect(floorCameraPosition(1, 4, 3)).toEqual([0, 8, 0]);
  });
  it("после остановки не вращается", () => {
    expect(floorCameraPosition(100, 4, 3)).toEqual(floorCameraPosition(1, 4, 3));
  });
});
