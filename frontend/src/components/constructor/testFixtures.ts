import type { ConstructorProductSize, GiftSizeProfile, SimpleGiftQuote } from "../../interfaces/giftConstructor";

export const testBox: GiftSizeProfile = {
  id: 4, code: "box-4x3", name: "Большая коробка", kind: "box", width_cells: 4, height_cells: 3,
  can_rotate: false, max_weight_grams: null, default_markup_amount: 50, simple_constructor_enabled: true,
  simple_requirements: { tea_count: 5, sweet_count: 2, total_items: 7, allow_duplicate_products: true },
};

export function testSize(id: number, role: "tea" | "sweet" = "tea", width = 1, height = 1): ConstructorProductSize {
  return { id, label: "50 г", constructor_role: role, product_quantity: 50,
    product: { id: id + 100, name: role === "tea" ? "Ассам" : "Пастила", price: 200, stock_unit: "gram", price_unit_quantity: 100, image: null },
    size: { ...testBox, id: 100 + id, kind: "item", width_cells: width, height_cells: height, can_rotate: true, simple_requirements: null },
  };
}

export const testSizes = [testSize(11), testSize(21, "sweet")];
export const testQuote: SimpleGiftQuote = {
  valid: true, box: testBox, layout: [], quantity: 1, required_products: {},
  totals: { currency: "RUB", products_total: 700, gift_markup_total: 50, promotion_discount: 30, personal_discount: 0, cart_discount: 0, shipping_cost: 0, shipping_discount: 0, final_total: 720 },
};
