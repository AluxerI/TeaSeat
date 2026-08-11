import type { LocalOrderItem, StockUnit } from "./types";

/** Товары со `stock_unit: "gram"` продаются шагом `sale_step` (например, 10 г),
 *  а цена задана за `price_unit_quantity` (например, 250 ₽ за 100 г). Считать
 *  их «штуками» — самая частая ошибка кассового интерфейса, поэтому вся
 *  арифметика количества собрана здесь. */

export function isWeighted(unit: StockUnit): boolean {
  return unit !== "piece";
}

export function unitLabel(unit: StockUnit): string {
  switch (unit) {
    case "gram":
      return "г";
    case "milliliter":
      return "мл";
    default:
      return "шт";
  }
}

/** Приводит количество к ближайшему допустимому: кратно шагу и не меньше шага. */
export function normalizeQuantity(quantity: number, saleStep: number): number {
  const step = Math.max(1, Math.trunc(saleStep) || 1);
  const rounded = Math.round(quantity / step) * step;
  return Math.max(step, rounded);
}

export function isValidQuantity(quantity: number, saleStep: number): boolean {
  const step = Math.max(1, Math.trunc(saleStep) || 1);
  return Number.isInteger(quantity) && quantity >= step && quantity % step === 0;
}

export function formatQuantity(quantity: number, unit: StockUnit): string {
  return `${quantity} ${unitLabel(unit)}`;
}

/** Цена позиции по локальному снимку. Только для предпросмотра: при
 *  синхронизации backend пересчитывает строки из подписанного `pricing_token`,
 *  включая автоматические акции, которых здесь нет. */
export function previewLineTotal(item: LocalOrderItem): number {
  const per = Math.max(1, item.price_unit_quantity || 1);
  return Math.round(((item.unit_price * item.quantity) / per) * 100) / 100;
}

export function previewOrderTotal(items: LocalOrderItem[]): number {
  const sum = items.reduce((acc, item) => acc + previewLineTotal(item), 0);
  return Math.round(sum * 100) / 100;
}

export function formatMoney(value: number): string {
  return `${value.toLocaleString("ru-RU", {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  })} ₽`;
}
