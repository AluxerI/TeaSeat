/**
 * Backend и CHECK-constraint базы данных поддерживают только две складские
 * единицы. `gram` означает продажу на вес, `piece` — продажу целыми штуками.
 */
export const STOCK_UNIT = {
  PIECE: "piece",
  GRAM: "gram",
} as const;

export type StockUnit = typeof STOCK_UNIT[keyof typeof STOCK_UNIT];

/** Сгруппированный prop, чтобы единица, шаг и база цены не расходились. */
export interface ProductMeasurement {
  stockUnit: StockUnit;
  saleStep: number;
  priceUnitQuantity: number;
}

export function isWeightedStockUnit(unit: StockUnit): boolean {
  return unit === STOCK_UNIT.GRAM;
}
