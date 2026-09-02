/** Только декоративная анимация; окончательную раскладку простого набора считает backend. */
export function packingSlot(index: number, count: number, width: number, depth: number) {
  const columns = Math.max(1, Math.ceil(Math.sqrt(Math.max(count, 1) * width / depth)));
  const rows = Math.max(1, Math.ceil(count / columns));
  const w = width / columns;
  const d = depth / rows;
  return { x: (index % columns + .5) * w - width / 2, z: (Math.floor(index / columns) + .5) * d - depth / 2,
    scale: Math.min(w / .85, d / .85, 1) * .8 };
}

export const packingDuration = (role: "tea" | "sweet") => role === "tea" ? 3.65 : 2.6;

export interface PackingEntry { id: number; role: "tea" | "sweet"; key: string; }

/** Повторения одного товара имеют разные стабильные ключи. Удаление последнего не перезапускает остальные. */
export function packingKeys(teas: number[], sweets: number[]): PackingEntry[] {
  return ([...teas.map((id) => ({ id, role: "tea" as const })), ...sweets.map((id) => ({ id, role: "sweet" as const }))])
    .map((item, index, all) => ({ ...item, key: `${item.role}:${item.id}:${all.slice(0, index).filter((previous) => previous.id === item.id && previous.role === item.role).length}` }));
}

/** Пропорции выбранной коробки сохраняются и в плоском SVG. */
export function packingBox(widthCells: number, heightCells: number) {
  const unit = Math.min(340 / widthCells, 220 / heightCells);
  const width = widthCells * unit, height = heightCells * unit;
  return { x: (480 - width) / 2, y: 136 + (220 - height) / 2, width, height };
}
