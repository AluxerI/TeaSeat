import type { CSSProperties } from "react";
import type { ConstructorProductSize, GiftSizeProfile, LayoutPlacement } from "../../interfaces/giftConstructor";
import { footprint } from "../../utils/giftLayout";
import { formatFootprint } from "../../utils/constructorInteraction";
import type { useConstructorDrag } from "../../hooks/useConstructorDrag";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

export interface FloorProps {
  box: GiftSizeProfile;
  sizes: ConstructorProductSize[];
  items: LayoutPlacement[];
  selectedId: string | null;
  onSelect: (id: string) => void;
  onCell: (x: number, y: number) => void;
  drag?: ReturnType<typeof useConstructorDrag>;
  cellSizeMm?: number | null;
}

/** Такой же редактор доступен без WebGL, а не только декоративная заглушка. */
export default function FloorGrid({ box, sizes, items, selectedId, onSelect, onCell, drag, cellSizeMm, overlay = false }: FloorProps & { overlay?: boolean }) {
  if (box.width_cells * box.height_cells > 1600) return <p>Большая сетка. Для размещения используйте координаты ниже.</p>;
  const ghost = drag?.preview;
  const ghostSize = sizes.find((size) => size.id === ghost?.placement.product_size_id);
  const [ghostWidth, ghostHeight] = ghostSize ? footprint(ghostSize, ghost!.placement.is_rotated) : [1, 1];
  return <div className={overlay ? styles.floorOverlayFrame : styles.floorFrame}>
    <div ref={drag?.surfaceRef} className={`${styles.floorGrid} ${overlay ? styles.floorOverlayGrid : ""}`} aria-label="Дно коробки, вид сверху" style={{
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
      return <button key={item.client_item_id} type="button" aria-label={`Позиция ${index + 1}: ${size.product.name}, ${formatFootprint(width, height, cellSizeMm)}`} aria-pressed={selectedId === item.client_item_id} onFocus={() => onSelect(item.client_item_id)} onClick={() => onSelect(item.client_item_id)}
        onPointerDown={(event) => drag?.startItem(item, event)} className={styles.floorItem} style={{ gridColumn: `${item.position_x + 1} / span ${width}`, gridRow: `${item.position_y + 1} / span ${height}` }}>
        <span>{index + 1}. {size.product.name}</span><small>{width} × {height} кл.</small>
      </button>;
    })}
    {ghost && <div aria-hidden="true" className={`${styles.dropGhost} ${ghost.valid ? styles.dropValid : styles.dropInvalid}`} style={{
      left: `${ghost.placement.position_x / box.width_cells * 100}%`, top: `${ghost.placement.position_y / box.height_cells * 100}%`,
      width: `${ghostWidth / box.width_cells * 100}%`, height: `${ghostHeight / box.height_cells * 100}%`,
    }}><span>{formatFootprint(ghostWidth, ghostHeight, cellSizeMm)}</span></div>}
    </div>
  </div>;
}
