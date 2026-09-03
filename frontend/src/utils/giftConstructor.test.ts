import { describe, expect, it } from "vitest";
import type { ConstructorProductSize } from "../interfaces/giftConstructor";
import {
  constructorItemPrice,
  constructorItemQuantity,
  constructorItemWeight,
  constructorHasStock,
  constructorRemainingStock,
  constructorStockLabel,
} from "./giftConstructor";

function item(overrides: Partial<ConstructorProductSize["product"]> = {}): ConstructorProductSize {
  return {
    id: 11,
    label: "Пакет 100 г",
    constructor_role: "tea",
    product_quantity: 100,
    product: {
      id: 7,
      name: "Ассам",
      price: 250,
      stock_unit: "gram",
      price_unit_quantity: 100,
      image: null,
      total_quantity: 250,
      ...overrides,
    },
    size: {} as ConstructorProductSize["size"],
  };
}

describe("constructor measurement", () => {
  it("рассчитывает только предварительную цену развесного формата", () => {
    expect(constructorItemPrice({ ...item(), product_quantity: 50 })).toBe(125);
    expect(constructorItemQuantity({ ...item(), product_quantity: 50 })).toBe("50 г");
  });

  it("форматирует штучный компонент отдельно от граммов", () => {
    const piece = { ...item({ stock_unit: "piece", price_unit_quantity: 1 }), product_quantity: 2 };
    expect(constructorItemPrice(piece)).toBe(500);
    expect(constructorItemQuantity(piece)).toBe("2 шт.");
  });

  it("использует количество как вес развесной позиции", () => {
    expect(constructorItemWeight({ ...item({ weight_grams: 900 }), product_quantity: 50 })).toBe(50);
  });

  it("рассчитывает вес штучной позиции по весу одной единицы", () => {
    const piece = {
      ...item({ stock_unit: "piece", price_unit_quantity: 1, weight_grams: 120 }),
      product_quantity: 2,
    };

    expect(constructorItemWeight(piece)).toBe(240);
  });

  it("не придумывает вес штучной позиции, если он не указан", () => {
    const piece = {
      ...item({ stock_unit: "piece", price_unit_quantity: 1, weight_grams: null }),
      product_quantity: 2,
    };

    expect(constructorItemWeight(piece)).toBeNull();
  });

  it("считает остаток по товару для разных форматов и повторов", () => {
    const small = { ...item(), id: 11, product_quantity: 50 };
    const large = { ...item(), id: 12, product_quantity: 100 };

    expect(constructorRemainingStock(large, [small.id, large.id], [small, large])).toBe(100);
    expect(constructorHasStock(large, [small.id, large.id], [small, large])).toBe(true);
    expect(constructorHasStock(large, [small.id, large.id, small.id], [small, large])).toBe(false);
  });

  it("форматирует онлайн-остаток в единице складского учёта", () => {
    expect(constructorStockLabel(item(), 150)).toBe("150 г");
    expect(constructorStockLabel(item({ stock_unit: "piece" }), 2)).toBe("2 шт.");
  });
});
