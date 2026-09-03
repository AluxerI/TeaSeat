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

export function constructorItemWeight(item: ConstructorProductSize): number | null {
  if (item.product.stock_unit === "gram") {
    return item.product_quantity;
  }

  const unitWeight = item.product.weight_grams;
  if (unitWeight === null || unitWeight === undefined || !Number.isFinite(unitWeight) || unitWeight < 0) {
    return null;
  }

  return Math.round(unitWeight * item.product_quantity);
}

export function constructorRemainingStock(
  item: ConstructorProductSize,
  selectedIds: number[],
  sizes: ConstructorProductSize[],
): number {
  const selectedQuantity = selectedIds.reduce((total, sizeId) => {
    const selected = sizes.find((size) => size.id === sizeId);
    return selected?.product.id === item.product.id
      ? total + selected.product_quantity
      : total;
  }, 0);

  return Math.max(0, item.product.total_quantity - selectedQuantity);
}

export function constructorHasStock(
  item: ConstructorProductSize,
  selectedIds: number[],
  sizes: ConstructorProductSize[],
): boolean {
  return constructorRemainingStock(item, selectedIds, sizes) >= item.product_quantity;
}

export function constructorStockLabel(item: ConstructorProductSize, quantity: number): string {
  const unit = item.product.stock_unit === "gram" ? "г" : "шт.";
  return `${quantity.toLocaleString("ru-RU")} ${unit}`;
}
