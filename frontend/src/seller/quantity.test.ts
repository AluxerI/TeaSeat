import { describe, expect, it } from "vitest";
import {
  formatMoney,
  formatQuantity,
  isWeighted,
  isValidQuantity,
  normalizeQuantity,
  previewLineTotal,
  previewOrderTotal,
  unitLabel,
} from "./quantity";
import type { LocalOrderItem } from "./types";

describe("normalizeQuantity", () => {
  it("округляет количество к ближайшему кратному шага", () => {
    expect(normalizeQuantity(15, 10)).toBe(20);
    expect(normalizeQuantity(14, 10)).toBe(10);
    expect(normalizeQuantity(30, 10)).toBe(30);
  });

  it("не опускается ниже шага", () => {
    expect(normalizeQuantity(0, 10)).toBe(10);
    expect(normalizeQuantity(-5, 10)).toBe(10);
    expect(normalizeQuantity(5, 10)).toBe(10);
  });

  it("переживает некорректный шаг", () => {
    expect(normalizeQuantity(5, 0)).toBe(5);
    expect(normalizeQuantity(7, -3)).toBe(7);
  });
});

describe("isValidQuantity", () => {
  it("требует целое число, кратное шагу и не меньше шага", () => {
    expect(isValidQuantity(20, 10)).toBe(true);
    expect(isValidQuantity(15, 10)).toBe(false);
    expect(isValidQuantity(5, 10)).toBe(false);
    expect(isValidQuantity(10.5, 10)).toBe(false);
  });
});

describe("unitLabel / isWeighted", () => {
  it("возвращает метку единицы", () => {
    expect(unitLabel("gram")).toBe("г");
    expect(unitLabel("milliliter")).toBe("мл");
    expect(unitLabel("piece")).toBe("шт");
  });

  it("считает весовое всё, кроме штук", () => {
    expect(isWeighted("gram")).toBe(true);
    expect(isWeighted("milliliter")).toBe(true);
    expect(isWeighted("piece")).toBe(false);
  });
});

describe("formatQuantity", () => {
  it("добавляет единицу к числу", () => {
    expect(formatQuantity(30, "gram")).toBe("30 г");
  });
});

function line(overrides: Partial<LocalOrderItem> = {}): LocalOrderItem {
  return {
    product_id: 1,
    quantity: 30,
    pricing_token: "tok",
    name: "Улун",
    unit_price: 250,
    price_unit_quantity: 100,
    stock_unit: "gram",
    sale_step: 10,
    ...overrides,
  };
}

describe("previewLineTotal", () => {
  it("считает цену пропорционально price_unit_quantity", () => {
    expect(previewLineTotal(line())).toBe(75);
  });

  it("без дроби, если цена за штуку", () => {
    expect(previewLineTotal(line({ quantity: 2, price_unit_quantity: 1, unit_price: 100 }))).toBe(200);
  });
});

describe("previewOrderTotal", () => {
  it("суммирует позиции без потери копеек", () => {
    const items = [line(), line({ product_id: 2, quantity: 1, unit_price: 1000, price_unit_quantity: 1 })];
    expect(previewOrderTotal(items)).toBe(1075);
  });

  it("пустой заказ = 0", () => {
    expect(previewOrderTotal([])).toBe(0);
  });
});

describe("formatMoney", () => {
  it("форматирует по ru-RU с символом ₽", () => {
    expect(formatMoney(100)).toBe("100 ₽");
    expect(formatMoney(100.5)).toBe("100,5 ₽");
    expect(formatMoney(100.55)).toBe("100,55 ₽");
  });
});
