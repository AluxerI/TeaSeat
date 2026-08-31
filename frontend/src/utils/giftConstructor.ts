import type { ConstructorProductSize } from "../interfaces/giftConstructor";

export function constructorItemPrice(item: ConstructorProductSize): number {
  return Math.round(
    (item.product.price * item.product_quantity / item.product.price_unit_quantity) * 100,
  ) / 100;
}
export function constructorItemQuantity(item: ConstructorProductSize): string {
  return item.product.stock_unit === "gram"
    ? `${item.product_quantity} г`
    : `${item.product_quantity} шт.`;
}
