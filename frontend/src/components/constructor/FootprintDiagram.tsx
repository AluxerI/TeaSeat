import type { GiftSizeProfile } from "../../interfaces/giftConstructor";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

interface Props {
  box: GiftSizeProfile;
  width: number;
  height: number;
  canRotate?: boolean;
  cellSizeMm?: number | null;
  className?: string;
  compact?: boolean;
}

/** Мини-карта показывает не абстрактное число, а долю дна, которую займёт формат. */
export default function FootprintDiagram({ box, width, height, canRotate = false, cellSizeMm, className = "", compact = false }: Props) {
  const columns = Math.max(1, box.width_cells);
  const rows = Math.max(1, box.height_cells);
  const frame = { x: 10, y: 8, width: 84, height: 56 };
  const itemWidth = Math.max(3, Math.min(frame.width, width / columns * frame.width));
  const itemHeight = Math.max(3, Math.min(frame.height, height / rows * frame.height));
  const itemX = frame.x + (frame.width - itemWidth) / 2;
  const itemY = frame.y + (frame.height - itemHeight) / 2;
  const showGrid = columns <= 12 && rows <= 12;
  const millimeters = cellSizeMm && Number.isFinite(cellSizeMm) && cellSizeMm > 0
    ? `, ${Number((width * cellSizeMm).toFixed(2))} на ${Number((height * cellSizeMm).toFixed(2))} миллиметров` : "";
  const label = `Занимает ${width} на ${height} клетки из дна ${columns} на ${rows}${millimeters}`;

  return <svg className={`${styles.footprintDiagram} ${compact ? styles.footprintCompact : ""} ${className}`} viewBox={compact ? "0 0 104 70" : "0 0 104 80"} role="img" aria-label={label}>
    <title>{label}</title>
    <rect className={styles.footprintBox} x={frame.x} y={frame.y} width={frame.width} height={frame.height} rx="5" />
    {showGrid && Array.from({ length: columns - 1 }, (_, index) => <line key={`x-${index}`} className={styles.footprintGridLine}
      x1={frame.x + frame.width * (index + 1) / columns} y1={frame.y} x2={frame.x + frame.width * (index + 1) / columns} y2={frame.y + frame.height} />)}
    {showGrid && Array.from({ length: rows - 1 }, (_, index) => <line key={`y-${index}`} className={styles.footprintGridLine}
      x1={frame.x} y1={frame.y + frame.height * (index + 1) / rows} x2={frame.x + frame.width} y2={frame.y + frame.height * (index + 1) / rows} />)}
    <rect className={styles.footprintItem} x={itemX} y={itemY} width={itemWidth} height={itemHeight} rx="3" />
    {!compact && <text className={styles.footprintLabel} x="52" y="76" textAnchor="middle">{width}×{height}</text>}
    {canRotate && <path className={styles.footprintRotate} d="M88 18a10 10 0 0 0-15-6m0 0 1-1-5m1 5 5-1" />}
  </svg>;
}
