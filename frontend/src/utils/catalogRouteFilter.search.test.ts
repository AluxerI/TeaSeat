import { describe, expect, it } from "vitest";
import { filterCatalogProducts, getCatalogQuery } from "./catalogRouteFilter";

const products = [
  { id: 1, name: "Ассам Золотой", brand: { name: "Tea House" }, description: "Крепкий чёрный чай", ingredients: "чай", rating_average: 4.8, category_path: { category: "Чай" } },
  { id: 2, name: "Эфиопия", brand: { name: "Roast Lab" }, description: "Кофе в зёрнах", rating_average: 4.6, category_path: { category: "Кофе" } },
];

describe("catalog route search", () => {
  it("читает q из URL", () => expect(getCatalogQuery("?q=%D0%B0%D1%81%D1%81%D0%B0%D0%BC")).toBe("ассам"));
  it("ищет по названию без учёта регистра", () => expect(filterCatalogProducts(products, "?q=%D0%90%D0%A1%D0%A1%D0%90%D0%9C").map((p) => p.id)).toEqual([1]));
  it("ищет по бренду", () => expect(filterCatalogProducts(products, "?q=roast").map((p) => p.id)).toEqual([2]));
  it("совмещает category и q", () => expect(filterCatalogProducts(products, "?category=%D0%A7%D0%B0%D0%B9&q=%D0%B0%D1%81%D1%81%D0%B0%D0%BC").map((p) => p.id)).toEqual([1]));
  it("требует совпадения всех слов", () => expect(filterCatalogProducts(products, "?q=%D0%BA%D1%80%D0%B5%D0%BF%D0%BA%D0%B8%D0%B9+%D0%B0%D1%81%D1%81%D0%B0%D0%BC").map((p) => p.id)).toEqual([1]));
});