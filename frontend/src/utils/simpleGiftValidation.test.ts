import { describe, expect, it } from "vitest";
import { testBox, testQuote, testSize, testSizes } from "../components/constructor/testFixtures";
import { simpleSelectionStatus, supportsSimple, verifiedSimpleQuote } from "./simpleGiftValidation";

const selection = { box_profile_id: 4, tea_product_size_ids: [11, 11, 11, 11, 11], sweet_product_size_ids: [21, 21] };

describe("simple selection and backend layout contract", () => {
  it("допускает повторения форматов и использует требования коробки, не 2+1", () => {
    expect(simpleSelectionStatus(testBox, testSizes, selection)).toEqual({ complete: true, error: "" });
    expect(simpleSelectionStatus(testBox, testSizes, { ...selection, tea_product_size_ids: [11, 11] })).toEqual({ complete: false, error: "" });
  });

  it.each([null, { tea_count: 0, sweet_count: 1 }, { tea_count: 1.5, sweet_count: 1 }, { tea_count: 40, sweet_count: 1 }])("отклоняет непригодные simple-настройки %s", (rule) => {
    expect(supportsSimple({ ...testBox, simple_requirements: rule && { ...rule, total_items: rule.tea_count + rule.sweet_count, allow_duplicate_products: true } })).toBe(false);
  });

  it("замечает недостаток площади ещё до выбора последнего товара", () => {
    const result = simpleSelectionStatus(testBox, [testSize(11, "tea", 3, 3), testSizes[1]], { ...selection, tea_product_size_ids: [11, 11], sweet_product_size_ids: [] });
    expect(result.complete).toBe(false);
    expect(result.error).toMatch(/мало места/);
  });

  it("не выдаёт сумму площадей за доказательство укладки или наличия", () => {
    const box = { ...testBox, width_cells: 3, height_cells: 3, simple_requirements: { tea_count: 1, sweet_count: 1, total_items: 2, allow_duplicate_products: true } };
    const sizes = [testSize(11, "tea", 2, 2), testSize(21, "sweet", 2, 2)];
    expect(simpleSelectionStatus(box, sizes, { ...selection, tea_product_size_ids: [11], sweet_product_size_ids: [21] }).error).toBe("");
    // Два квадрата 2×2 не влезают в 3×3, хотя 8 < 9: окончательное решение у quote.
  });

  it("проверяет обе стороны и can_rotate", () => {
    const size = testSize(11, "tea", 3, 4);
    const partial = { ...selection, tea_product_size_ids: [11], sweet_product_size_ids: [] };
    expect(simpleSelectionStatus(testBox, [size], partial).error).toBe("");
    expect(simpleSelectionStatus(testBox, [{ ...size, size: { ...size.size, can_rotate: false } }], partial).error).toMatch(/не помещается/);
  });

  it("не путает product.id с product_size.id и не принимает чужую роль", () => {
    expect(simpleSelectionStatus(testBox, testSizes, { ...selection, tea_product_size_ids: [111] }).error).toMatch(/больше недоступен/);
    expect(simpleSelectionStatus(testBox, testSizes, { ...selection, tea_product_size_ids: [21] }).error).toMatch(/больше недоступен/);
  });

  it("проверяет точное количество форматов", () => {
    expect(simpleSelectionStatus(testBox, testSizes, { ...selection, tea_product_size_ids: [11, 11, 11, 11, 11, 11] }).error).toMatch(/больше позиций/);
  });

  it("принимает подтверждённые позиции в порядке запроса, включая повторения", () => {
    const result = verifiedSimpleQuote(testQuote, testBox, testSizes, selection);
    expect(result.layout.map((item) => item.product_size_id)).toEqual([...selection.tea_product_size_ids, ...selection.sweet_product_size_ids]);
    expect(result.layout).toHaveLength(7);
  });

  it.each([
    { valid: false }, { quantity: 2 }, { box: { ...testBox, id: 5 } }, { box: { ...testBox, width_cells: 9 } },
    { totals: { ...testQuote.totals, final_total: NaN } }, { totals: { ...testQuote.totals, final_total: -1 } }, { layout: [] },
  ])("не принимает неподходящий расчёт %s", (changes) => {
    expect(() => verifiedSimpleQuote({ ...testQuote, ...changes } as typeof testQuote, testBox, testSizes, selection)).toThrow(/Некорректный ответ API/);
  });

  it.each([
    { product_size_id: 21 }, { product_id: 999 }, { product_quantity: 1 }, { sort_order: 9 }, { client_item_id: "" },
    { position_x: -1 }, { position_x: .5 }, { position_x: 4 }, { position_y: 3 }, { is_rotated: "false" }, { width_cells: 2 },
    { client_item_id: "test-position-1" }, { position_x: 1 },
  ])("отклоняет повреждённую позицию %s", (changes) => {
    const quote = { ...testQuote, layout: [{ ...testQuote.layout[0], ...changes }, ...testQuote.layout.slice(1)] };
    expect(() => verifiedSimpleQuote(quote, testBox, testSizes, selection)).toThrow(/Некорректный ответ API/);
  });

  it("принимает уже повёрнутые width/height из ответа, не переставляя их ещё раз", () => {
    const box = { ...testBox, simple_requirements: { tea_count: 1, sweet_count: 1, total_items: 2, allow_duplicate_products: true } };
    const sizes = [testSize(11, "tea", 2, 1), testSizes[1]];
    const quote = { ...testQuote, box, layout: [
      { ...testQuote.layout[0], width_cells: 1, height_cells: 2, is_rotated: true },
      { ...testQuote.layout[1], product_size_id: 21, product_id: 121 },
    ] };
    const result = verifiedSimpleQuote(quote, box, sizes, { ...selection, tea_product_size_ids: [11], sweet_product_size_ids: [21] });
    expect(result.layout[0]).toMatchObject({ width_cells: 1, height_cells: 2, is_rotated: true });
  });
});
