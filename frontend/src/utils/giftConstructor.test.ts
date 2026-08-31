import { describe, expect, it } from "vitest";
import type { ConstructorProductSize } from "../interfaces/giftConstructor";
import { constructorItemPrice, constructorItemQuantity } from "./giftConstructor";

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
});
