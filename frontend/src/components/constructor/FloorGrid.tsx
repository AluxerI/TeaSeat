import type { CSSProperties } from "react";
import type { ConstructorProductSize, GiftSizeProfile, LayoutPlacement } from "../../interfaces/giftConstructor";
import { footprint } from "../../utils/giftLayout";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

export interface FloorProps {
  box: GiftSizeProfile;
  sizes: ConstructorProductSize[];
  items: LayoutPlacement[];
  selectedId: string | null;
  onSelect: (id: string) => void;
  onCell: (x: number, y: number) => void;
}

/** Такой же редактор доступен без WebGL, а не только декоративная заглушка. */
export default function FloorGrid({ box, sizes, items, selectedId, onSelect, onCell }: FloorProps) {
  if (box.width_cells * box.height_cells > 1600) return <p>Большая сетка. Для размещения используйте координаты ниже.</p>;
  return <div className={styles.floorGrid} aria-label="Дно коробки, вид сверху" style={{
    gridTemplateColumns: `repeat(${box.width_cells}, minmax(0, 1fr))`,
    gridTemplateRows: `repeat(${box.height_cells}, minmax(0, 1fr))`,
    aspectRatio: `${box.width_cells}/${box.height_cells}`,
  } as CSSProperties}>
    {Array.from({ length: box.width_cells * box.height_cells }, (_, index) => {
      const x = index % box.width_cells;
      const y = Math.floor(index / box.width_cells);
      return <button key={index} type="button" className={styles.floorCell} aria-label={`Ячейка ${x + 1}, ${y + 1}`} onClick={() => onCell(x, y)} style={{ gridColumn: x + 1, gridRow: y + 1 }} />;
    })}
    {items.map((item, index) => {
      const size = sizes.find((candidate) => candidate.id === item.product_size_id);
      if (!size) return null;
      const [width, height] = footprint(size, item.is_rotated);
      return <button key={item.client_item_id} type="button" aria-pressed={selectedId === item.client_item_id} onClick={() => onSelect(item.client_item_id)} className={styles.floorItem} style={{ gridColumn: `${item.position_x + 1} / span ${width}`, gridRow: `${item.position_y + 1} / span ${height}` }}>
        {index + 1}. {size.product.name}
      </button>;
    })}
  </div>;
}
