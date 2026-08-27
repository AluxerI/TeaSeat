import { STOCK_UNIT, type StockUnit } from "../types/productMeasurement";

/**
 * Общие правила количества для карточки и быстрого просмотра.
 * Backend всё равно выполняет окончательную проверку, но одинаковые функции
 * не дают двум интерфейсам по-разному округлять один и тот же остаток.
 */

export function getSafeSaleStep(saleStep?: number): number {
  return Math.max(1, Math.floor(saleStep ?? 1));
}
export function getSafePriceUnitQuantity(priceUnitQuantity?: number): number {
  return Math.max(1, Math.floor(priceUnitQuantity ?? 1));
}

/** Округляет складской остаток вниз до количества, кратного шагу продажи. */
export function getMaximumQuantity(availableQuantity: number, saleStep: number): number {
  const step = getSafeSaleStep(saleStep);
  const safeAvailable = Math.max(0, Math.floor(availableQuantity));
  return Math.floor(safeAvailable / step) * step;
}

/** Выбирает стартовое количество, удобное для штучного и развесного товара. */
export function getInitialQuantity(
  stockUnit: StockUnit,
  saleStep: number,
  priceUnitQuantity: number,
  maximumQuantity: number,
): number {
  const step = getSafeSaleStep(saleStep);
  if (maximumQuantity < step) return 0;

  const preferred = stockUnit === STOCK_UNIT.GRAM
    ? Math.max(step, getSafePriceUnitQuantity(priceUnitQuantity))
    : step;
  const aligned = Math.ceil(preferred / step) * step;
  return Math.min(aligned, maximumQuantity);
}

export function getProductUnitLabel(stockUnit: StockUnit): string {
  return stockUnit === STOCK_UNIT.GRAM ? "г" : "шт.";
}

export function isAllowedQuantity(quantity: number, step: number, maximum: number): boolean {
  const safeStep = getSafeSaleStep(step);
  return quantity >= safeStep && quantity <= maximum && quantity % safeStep === 0;
}

/** Цена в каталоге задана за price_unit_quantity, а не всегда за одну штуку. */
export function calculateShownPrice(
  unitPrice: number,
  quantity: number,
  priceUnitQuantity: number,
): number {
  return unitPrice * quantity / getSafePriceUnitQuantity(priceUnitQuantity);
}
