import type { ConstructorProductSize, GiftSizeProfile, LayoutPlacement } from "../interfaces/giftConstructor";

export const MAX_LAYOUT_ITEMS = 40;

export function footprint(size: ConstructorProductSize, rotated: boolean): [number, number] {
  return rotated ? [size.size.height_cells, size.size.width_cells] : [size.size.width_cells, size.size.height_cells];
}

/** Только геометрическая подсказка UI. Вес, наличие и окончательное решение — на backend. */
export function layoutError(box: GiftSizeProfile, sizes: ConstructorProductSize[], items: LayoutPlacement[]): string | null {
  if (items.length > MAX_LAYOUT_ITEMS) return `Максимум ${MAX_LAYOUT_ITEMS} позиций в подарке.`;
  const occupied: { x: number; y: number; width: number; height: number }[] = [];
  const ids = new Set<string>();
  for (const item of items) {
    const size = sizes.find((candidate) => candidate.id === item.product_size_id);
    if (!size) return "Формат товара больше недоступен. Обновите состав.";
    if (ids.has(item.client_item_id)) return "Повтор идентификатора позиции.";
    ids.add(item.client_item_id);
    if (item.is_rotated && !size.size.can_rotate) return "Этот формат нельзя поворачивать.";
    const [width, height] = footprint(size, item.is_rotated);
    const x = item.position_x;
    const y = item.position_y;
    if (!Number.isInteger(x) || !Number.isInteger(y) || x < 0 || y < 0
      || x + width > box.width_cells || y + height > box.height_cells) return "Позиция выходит за границы коробки.";
    // Проверяем прямоугольники, не перебирая каждую ячейку большой коробки.
    if (occupied.some((other) => x < other.x + other.width && x + width > other.x
      && y < other.y + other.height && y + height > other.y)) return "Позиции перекрываются. Выберите свободные ячейки.";
    occupied.push({ x, y, width, height });
  }
  return null;
}

export function firstFreePlacement(box: GiftSizeProfile, sizes: ConstructorProductSize[], items: LayoutPlacement[], size: ConstructorProductSize, id: string): LayoutPlacement | null {
  if (items.length >= MAX_LAYOUT_ITEMS || layoutError(box, sizes, items)) return null;
  // Свободный прямоугольник можно сдвинуть к границе коробки или краю предмета.
  // Кандидаты зависят от числа предметов, а не от площади сетки.
  const columns = new Set([0]);
  const rows = new Set([0]);
  for (const item of items) {
    const existing = sizes.find((candidate) => candidate.id === item.product_size_id)!;
    const [width, height] = footprint(existing, item.is_rotated);
    columns.add(item.position_x + width);
    rows.add(item.position_y + height);
  }
  const candidateX = [...columns].sort((a, b) => a - b);
  const candidateY = [...rows].sort((a, b) => a - b);
  for (const rotated of size.size.can_rotate ? [false, true] : [false]) {
    const [width, height] = footprint(size, rotated);
    for (const y of candidateY) {
      if (y + height > box.height_cells) continue;
      for (const x of candidateX) {
        if (x + width > box.width_cells) continue;
        const item = { client_item_id: id, product_size_id: size.id, position_x: x, position_y: y, is_rotated: rotated };
        if (!layoutError(box, sizes, [...items, item])) return item;
      }
    }
  }
  return null;
}
