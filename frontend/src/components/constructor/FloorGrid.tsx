import { useRef, type CSSProperties } from "react";
import RotateRightRounded from "@mui/icons-material/RotateRightRounded";
import type { ConstructorProductSize, GiftSizeProfile, LayoutPlacement } from "../../interfaces/giftConstructor";
import { footprint } from "../../utils/giftLayout";
import { formatFootprint } from "../../utils/constructorInteraction";
import type { useConstructorDrag } from "../../hooks/useConstructorDrag";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";
import ProductTexture from "./ProductTexture";
import { useItemRotation } from "../../hooks/useItemRotation";

export interface FloorProps {
  box: GiftSizeProfile;
  sizes: ConstructorProductSize[];
  items: LayoutPlacement[];
  selectedId: string | null;
  onSelect: (id: string) => void;
  onCell: (x: number, y: number) => void;
  drag?: ReturnType<typeof useConstructorDrag>;
  cellSizeMm?: number | null;
  onRotate?: (id: string, rotated: boolean) => void;
  onDelete?: (id: string) => void;
  onTransformBusy?: (busy: boolean) => void;
}

/** Такой же редактор доступен без WebGL, а не только декоративная заглушка. */
export default function FloorGrid({ box, sizes, items, selectedId, onSelect, onCell, drag, cellSizeMm, onRotate, onDelete, onTransformBusy, overlay = false }: FloorProps & { overlay?: boolean }) {
  const frame = useRef<HTMLDivElement>(null);
  const rotation = useItemRotation(onRotate, onTransformBusy);
  if (box.width_cells * box.height_cells > 1600) return <p role="alert">Эта коробка слишком велика для редактора. Выберите меньшую.</p>;
  const selected = items.find((item) => item.client_item_id === selectedId);
  const selectedSize = sizes.find((size) => size.id === selected?.product_size_id);
  const selectionBounds = selected && selectedSize ? footprint(selectedSize, selected.is_rotated) : null;
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
      return <button key={index} type="button" className={styles.floorCell} tabIndex={-1} disabled={rotation.active} aria-label={`Ячейка ${x + 1}, ${y + 1}`} onClick={() => onCell(x, y)} style={{ gridColumn: x + 1, gridRow: y + 1 }} />;
    })}
    {items.map((item, index) => {
      const size = sizes.find((candidate) => candidate.id === item.product_size_id);
      if (!size) return null;
      const [width, height] = footprint(size, item.is_rotated);
      return <button key={item.client_item_id} type="button" aria-label={`Позиция ${index + 1}: ${size.product.name}, ${formatFootprint(width, height, cellSizeMm)}`} aria-pressed={selectedId === item.client_item_id} onFocus={() => onSelect(item.client_item_id)} onClick={() => onSelect(item.client_item_id)}
        onPointerDown={(event) => { if (!rotation.active) drag?.startItem(item, event); }}
        onKeyDown={(event) => {
          if (rotation.active || drag?.dragging) return;
          if (event.key === "Delete" || event.key === "Backspace") { event.preventDefault(); onDelete?.(item.client_item_id); }
          if (event.key === "r" || event.key === "R") { event.preventDefault(); if (size.size.can_rotate) onRotate?.(item.client_item_id, !item.is_rotated); }
          const direction = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[event.key];
          if (direction) { event.preventDefault(); onCell(item.position_x + direction[0], item.position_y + direction[1]); }
        }} className={styles.floorItem} style={{ gridColumn: `${item.position_x + 1} / span ${width}`, gridRow: `${item.position_y + 1} / span ${height}`,
          transform: selectedId === item.client_item_id && rotation.active ? `rotate(${rotation.preview}deg)` : undefined }}>
        <ProductTexture size={size} rotated={item.is_rotated} />
      </button>;
    })}
    {selected && selectedSize && selectionBounds && !drag?.dragging && <div ref={frame} className={styles.transformFrame} aria-label="Рамка выделения" style={{
      left: `${selected.position_x / box.width_cells * 100}%`, top: `${selected.position_y / box.height_cells * 100}%`,
      width: `${selectionBounds[0] / box.width_cells * 100}%`, height: `${selectionBounds[1] / box.height_cells * 100}%`,
    }}>
      <span className={styles.transformCorners} aria-hidden="true" />
      <button type="button" className={styles.rotationHandle} aria-label="Повернуть предмет за ручку" title="Потяните для поворота с шагом 90°"
        disabled={!selectedSize.size.can_rotate} onPointerDown={(event) => { if (frame.current) rotation.start(event, selected, frame.current.getBoundingClientRect()); }}
        onKeyDown={(event) => { if (["ArrowLeft", "ArrowRight", "Enter", " "].includes(event.key) && !rotation.active) { event.preventDefault(); onRotate?.(selected.client_item_id, !selected.is_rotated); } }}>
        <RotateRightRounded fontSize="small" />
      </button>
    </div>}
    {ghost && ghostSize && <div aria-hidden="true" className={`${styles.dropGhost} ${ghost.valid ? styles.dropValid : styles.dropInvalid}`} style={{
      left: `${ghost.placement.position_x / box.width_cells * 100}%`, top: `${ghost.placement.position_y / box.height_cells * 100}%`,
      width: `${ghostWidth / box.width_cells * 100}%`, height: `${ghostHeight / box.height_cells * 100}%`,
    }}><ProductTexture size={ghostSize} rotated={ghost.placement.is_rotated} /></div>}
    </div>
  </div>;
}
