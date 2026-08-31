import { beforeEach, describe, expect, it } from "vitest";
import { db, resetSellerDatabase } from "./db";
import { replaceCachedProducts } from "./bootstrap";
import type { LocalProduct } from "./types";

function product(id: number): LocalProduct {
  return {
    id,
    name: `Товар ${id}`,
    stock_unit: "piece",
    sale_step: 1,
    price_unit_quantity: 1,
    pricing: {
      unit_price: 100,
      issued_at: "2026-01-01T00:00:00Z",
      expires_at: "2026-02-01T00:00:00Z",
      automatic_promotions: [],
    },
    pricing_token: `token-${id}`,
    stock: {
      quantity: 10,
      reserved_online_quantity: 0,
      reserved_seller_quantity: 0,
      available_quantity: 10,
      shortage_quantity: 0,
    },
    warehouse_id: 1,
    image: null,
  };
}

beforeEach(resetSellerDatabase);

describe("replaceCachedProducts", () => {
  it("удаляет товары, которых больше нет в новом снимке точки", async () => {
    await db.products.bulkPut([product(1), product(2)]);
    await replaceCachedProducts(1, [product(2)]);

    const cached = await db.products.where("warehouse_id").equals(1).toArray();
    expect(cached.map((item) => item.id)).toEqual([2]);
  });
});
