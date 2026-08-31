import type { ConstructorProductSize, GiftSizeProfile, LayoutPlacement, SimpleGiftQuote, SimpleGiftSelection } from "../interfaces/giftConstructor";
import { footprint, layoutError, MAX_LAYOUT_ITEMS } from "./giftLayout";

export function supportsSimple(box: GiftSizeProfile): boolean {
  const rule = box.simple_requirements;
  return Boolean(box.simple_constructor_enabled && rule && Number.isSafeInteger(rule.tea_count)
    && rule.tea_count > 0 && Number.isSafeInteger(rule.sweet_count) && rule.sweet_count > 0
    && rule.tea_count + rule.sweet_count <= MAX_LAYOUT_ITEMS);
}

/** Только необходимые условия. Равная площадь НЕ доказывает, что прямоугольники уложатся. */
export function simpleSelectionStatus(box: GiftSizeProfile, sizes: ConstructorProductSize[], selection: SimpleGiftSelection) {
  const teas = selection.tea_product_size_ids, sweets = selection.sweet_product_size_ids;
  const rule = box.simple_requirements;
  const complete = Boolean(rule && teas.length === rule.tea_count && sweets.length === rule.sweet_count);
  if (!supportsSimple(box)) return { complete, error: "Для этой коробки не настроена быстрая сборка. Выберите другой размер или свою композицию." };
  if (teas.length > rule!.tea_count || sweets.length > rule!.sweet_count) return { complete, error: "Выбрано больше позиций, чем предусмотрено для этой коробки." };
  let area = 0;
  for (const [ids, role] of [[teas, "tea"], [sweets, "sweet"]] as const) {
    for (const id of ids) {
      const format = sizes.find((candidate) => candidate.id === id);
      if (!format || format.constructor_role !== role) return { complete, error: "Выбранный формат больше недоступен. Обновите состав." };
      const [width, height] = footprint(format, false);
      const fits = width <= box.width_cells && height <= box.height_cells;
      const fitsRotated = format.size.can_rotate && height <= box.width_cells && width <= box.height_cells;
      if (!fits && !fitsRotated) return { complete, error: "Формат «" + format.product.name + "» не помещается в выбранную коробку." };
      area += width * height;
    }
  }
  if (area > box.width_cells * box.height_cells) return { complete, error: "Для этих форматов мало места. Выберите меньшие упаковки или коробку больше." };
  return { complete, error: "" };
}

export interface SimplePackingPlacement extends LayoutPlacement {
  /** Backend уже поменял стороны при is_rotated=true; второй раз их не переставляем. */
  width_cells: number;
  height_cells: number;
}

/** Не принимаем цену от чужого состава или геометрически некорректную раскладку. */
export function verifiedSimpleQuote(quote: SimpleGiftQuote, box: GiftSizeProfile, sizes: ConstructorProductSize[], selection: SimpleGiftSelection) {
  const invalid = () => new Error("Некорректный ответ API: расчёт не соответствует выбранному составу или размерам коробки. Обновите каталог и повторите проверку.");
  const ids = [...selection.tea_product_size_ids, ...selection.sweet_product_size_ids];
  if (quote?.valid !== true || quote.quantity !== 1 || quote.box?.id !== box.id
    || quote.box.width_cells !== box.width_cells || quote.box.height_cells !== box.height_cells
    || !Number.isFinite(quote.totals?.final_total) || quote.totals.final_total < 0
    || !Array.isArray(quote.layout) || quote.layout.length !== ids.length) throw invalid();
  const layout: SimplePackingPlacement[] = quote.layout.map((item, index) => {
    const format = sizes.find((candidate) => candidate.id === ids[index]);
    if (!item || !format || item.product_size_id !== format.id || item.product_id !== format.product.id
      || item.product_quantity !== format.product_quantity || item.sort_order !== index
      || typeof item.client_item_id !== "string" || !item.client_item_id
      || typeof item.is_rotated !== "boolean" || typeof item.position_x !== "number" || typeof item.position_y !== "number"
      || !Number.isSafeInteger(item.position_x) || !Number.isSafeInteger(item.position_y)) throw invalid();
    const [width, height] = footprint(format, item.is_rotated);
    if (item.width_cells !== width || item.height_cells !== height) throw invalid();
    return { client_item_id: item.client_item_id, product_size_id: format.id, position_x: item.position_x, position_y: item.position_y,
      is_rotated: item.is_rotated, width_cells: width, height_cells: height };
  });
  if (layoutError(box, sizes, layout)) throw invalid();
  return { quote, layout };
}
