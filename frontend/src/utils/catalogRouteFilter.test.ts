import { describe, expect, it } from "vitest";
import { filterCatalogProducts, getCatalogRouteCategory } from "./catalogRouteFilter";

const products = [
  { id: 1, rating_average: 4.8, category_path: { category: { name: "Напитки" }, subcategory: { name: "Чайные наборы" } } },
  { id: 2, rating_average: 4.9, category_path: { category: { name: "Напитки" }, subcategory: { name: "Кофейные наборы" } } },
  { id: 3, rating_average: 3.7, category_path: { category: { name: "Сладости" } } },
];

describe("catalog route filter", () => {
  it("читает выбранную категорию из URL", () => {
    expect(getCatalogRouteCategory("?category=%D0%A7%D0%B0%D0%B9")).toBe("Чай");
  });

  it("ищет категорию по всему category_path, а не только по верхнему level", () => {
    expect(filterCatalogProducts(products, "?category=Чай").map((item) => item.id)).toEqual([1]);
  });

  it("комбинирует категорию страницы с рейтингом", () => {
    expect(filterCatalogProducts(products, "?category=Сладости", 4).map((item) => item.id)).toEqual([]);
  });
});