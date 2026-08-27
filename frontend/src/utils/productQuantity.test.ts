import { describe, expect, it } from "vitest";
import {
  calculateShownPrice,
  getInitialQuantity,
  getMaximumQuantity,
  isAllowedQuantity,
} from "./productQuantity";

describe("product quantity rules", () => {
  it("не предлагает непродаваемый хвост складского остатка", () => {
    expect(getMaximumQuantity(168, 50)).toBe(150);
    expect(isAllowedQuantity(200, 50, 150)).toBe(false);
  });

  it("выбирает базовые 100 г, но не превышает остаток", () => {
    expect(getInitialQuantity("gram", 10, 100, 340)).toBe(100);
    expect(getInitialQuantity("gram", 50, 100, 50)).toBe(50);
  });

  it("считает цену относительно количества, за которое она указана", () => {
    expect(calculateShownPrice(250, 150, 100)).toBe(375);
  });
});
