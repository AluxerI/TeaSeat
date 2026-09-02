import { useMemo, type CSSProperties, type MutableRefObject } from "react";
import type { GiftSizeProfile } from "../../interfaces/giftConstructor";
import { usePackingQueue } from "../../hooks/usePackingQueue";
import { packingBox, packingDuration, packingKeys, packingSlot } from "../../utils/simplePacking";
import type { SimplePackingPlacement } from "../../utils/simpleGiftValidation";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

/** Векторные иллюстрации упаковки. Товар и его формат выбираются в соседнем каталоге. */
function TeaArtwork() {
  return <>
    <ellipse className={styles.packingShadow} cx="0" cy="35" rx="26" ry="5" />
    <rect className={styles.packingPaper} x="-26" y="-28" width="52" height="66" rx="7" />
    <rect className={styles.packingTeaFill} x="-21" y="-22" width="42" height="54" rx="4" />
    <path className={styles.packingTeaMark} d="M-9 17C-15 3-3-7 11-8C14 9 2 20-9 17ZM-9 17 7-3" />
    <g transform="translate(0 -28)"><path className={styles.packingTeaFlap} d="M-26 0 0 20 26 0Z" /></g>
    <path className={styles.packingStitch} d="M-20 33H20" />
    <g className={styles.packingLeaves}>
      {Array.from({ length: 7 }, (_, index) => <ellipse key={index} cx={((index * 7) % 15) - 7} cy={-67 + index * 5} rx="2.5" ry="4" />)}
    </g>
  </>;
}

function SweetArtwork() {
  return <>
    <ellipse className={styles.packingShadow} cx="0" cy="28" rx="33" ry="5" />
    <rect className={styles.packingChocolate} x="-28" y="-21" width="56" height="42" rx="5" />
    <path className={styles.packingChocolateLines} d="M-9-18V18M9-18V18M-24 0H24" />
    <g className={styles.packingWrapLeft}><path className={styles.packingFoil} d="M-36-25H0V25H-36L-30 17-36 8-30 0-36-9-30-17Z" /></g>
    <g className={styles.packingWrapRight}><path className={styles.packingFoil} d="M36-25H0V25H36L30 17 36 8 30 0 36-9 30-17Z" /></g>
    <g className={styles.packingRibbon}>
      <path d="M-4-25H4V25H-4ZM-30-4H30V4H-30Z" />
      <path className={styles.packingBow} d="M0 0C-26 0-20-23 0-7C20-23 26 0 0 0Z" />
    </g>
  </>;
}

export interface SimplePackingSceneProps {
  box: GiftSizeProfile; teas: number[]; sweets: number[]; seen: MutableRefObject<Set<string>>;
  layout?: SimplePackingPlacement[];
}

export default function SimplePackingScene({ box, teas, sweets, seen, layout }: SimplePackingSceneProps) {
  const entries = useMemo(() => packingKeys(teas, sweets), [teas, sweets]);
  const queue = usePackingQueue(entries, seen);
  const bounds = packingBox(box.width_cells, box.height_cells);
  const teaSlots = box.simple_requirements?.tea_count ?? teas.length;
  const slots = teaSlots + (box.simple_requirements?.sweet_count ?? sweets.length);
  const unit = bounds.width / box.width_cells;
  return <div className={styles.packing2d} data-paused={queue.paused} data-layout={layout ? "server" : "illustration"} aria-label="Анимация упаковки подарка">
    <svg className={styles.packingCanvas} viewBox="0 0 480 410" role="img" aria-label={"2D-упаковка: " + box.name}>
      <rect className={styles.packingBoxShadow} x={bounds.x - 14} y={bounds.y - 7} width={bounds.width + 28} height={bounds.height + 28} rx="13" />
      <rect className={styles.packingBoxOuter} x={bounds.x - 14} y={bounds.y - 14} width={bounds.width + 28} height={bounds.height + 28} rx="12" />
      <rect className={styles.packingBoxInner} x={bounds.x - 5} y={bounds.y - 5} width={bounds.width + 10} height={bounds.height + 10} rx="6" />
      <rect className={styles.packingBoxFloor} x={bounds.x} y={bounds.y} width={bounds.width} height={bounds.height} rx="4" />
      {layout?.map((item) => {
        const gap = Math.min(1, item.width_cells * unit / 10, item.height_cells * unit / 10);
        return <rect key={item.client_item_id} className={styles.packingFootprint} data-footprint={item.client_item_id}
          x={bounds.x + item.position_x * unit + gap} y={bounds.y + item.position_y * unit + gap}
          width={item.width_cells * unit - 2 * gap} height={item.height_cells * unit - 2 * gap} rx="3" />;
      })}
      {entries.map((entry, index) => {
        const slot = packingSlot(entry.role === "tea" ? index : teaSlots + index - teas.length, slots, bounds.width / 100, bounds.height / 100);
        const placement = layout?.[index];
        const artwork = entry.role === "tea" ? [68, 80] : [80, 72];
        const [artWidth, artHeight] = placement?.is_rotated ? [artwork[1], artwork[0]] : artwork;
        const x = placement ? (placement.position_x + placement.width_cells / 2) * unit : bounds.width / 2 + slot.x * 100;
        const y = placement ? (placement.position_y + placement.height_cells / 2) * unit : bounds.height / 2 + slot.z * 100;
        const scale = placement ? Math.min(placement.width_cells * unit / artWidth, placement.height_cells * unit / artHeight) * .9 : slot.scale;
        const state = seen.current.has(entry.key) || queue.reduced ? "packed" : queue.activeKey === entry.key ? "active" : "queued";
        return <g key={entry.key} className={styles.packingActor} data-packing-key={entry.key} data-role={entry.role} data-state={state}
          style={{ "--pack-x": (bounds.x + x) + "px", "--pack-y": (bounds.y + y) + "px",
            "--pack-scale": scale, "--pack-duration": packingDuration(entry.role) + "s" } as CSSProperties}
          onAnimationEnd={(event) => { if (event.target === event.currentTarget && state === "active") queue.complete(entry.key); }}>
          <g transform={placement?.is_rotated ? "rotate(90)" : undefined}>{entry.role === "tea" ? <TeaArtwork /> : <SweetArtwork />}</g>
        </g>;
      })}
      {/* Верхняя кромка переднего борта лежит над содержимым, а не под ним. */}
      <path className={styles.packingBoxRim} d={"M" + (bounds.x - 9) + " " + (bounds.y + bounds.height + 7) + "H" + (bounds.x + bounds.width + 9)} />
    </svg>
    <span className={styles.srOnly}>{layout ? "Расположение и размеры соответствуют раскладке сервера." : "Условная анимация упаковки; точную раскладку проверяет сервер."}</span>
  </div>;
}
