import ArrowForwardRounded from "@mui/icons-material/ArrowForwardRounded";
import ViewInArOutlined from "@mui/icons-material/ViewInArOutlined";
import AutoAwesomeOutlined from "@mui/icons-material/AutoAwesomeOutlined";
import CheckRounded from "@mui/icons-material/CheckRounded";
import type { GiftSizeProfile } from "../../interfaces/giftConstructor";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

export type ConstructorMode = "simple" | "advanced";
const ConstructorBoxScene = lazy(() => import("./ConstructorBoxScene"));

export function supportsSimple(box: GiftSizeProfile): boolean {
  const rule = box.simple_requirements;
  return Boolean(box.simple_constructor_enabled && rule && Number.isSafeInteger(rule.tea_count)
    && rule.tea_count > 0 && Number.isSafeInteger(rule.sweet_count) && rule.sweet_count > 0);
}

/** Иллюстрация интерфейса, не фиктивный товар: размеры и список коробок приходят из API. */
function BoxArtwork({ flat = false }: { flat?: boolean }) {
  return <svg viewBox="0 0 240 170" aria-hidden="true" className={styles.boxArtwork}>
    {flat ? <>
      <rect x="38" y="22" width="164" height="126" rx="14" fill="#d7b68e" />
      <rect x="47" y="31" width="146" height="108" rx="8" fill="#f4e9d7" />
      <rect x="57" y="41" width="57" height="88" rx="8" fill="#687e4c" />
      <path d="M74 67q21-20 21 0t-21 29q-15-12 0-29" fill="#c6d4a7" />
      <rect x="124" y="41" width="59" height="39" rx="8" fill="#b7755f" />
      <rect x="124" y="90" width="59" height="39" rx="8" fill="#dec887" />
    </> : <>
      <ellipse cx="123" cy="143" rx="87" ry="12" fill="#513624" opacity=".1" />
      <path d="m33 70 99-39 78 38-99 43Z" fill="#eed9b9" />
      <path d="m33 70 78 42v40l-78-43Z" fill="#bc9166" />
      <path d="m111 112 99-43v40l-99 43Z" fill="#d5ad80" />
      <path d="m87 48 79 40v40l-19 8V97L67 56Z" fill="#62764c" />
      <path d="m65 88 96-40 18 9-96 41v39l-18-10Z" fill="#78905a" />
      <path d="M122 72C77 73 91 34 122 62c30-44 61-1 0 10Z" fill="none" stroke="#4f653c" strokeWidth="9" strokeLinejoin="round" />
    </>}
  </svg>;
}

export function ConstructorModePicker({ box, onSelect }: { box: GiftSizeProfile; onSelect: (mode: ConstructorMode) => void }) {
  return <section className={styles.choiceSection} aria-label="Выбор конструктора">
    <h2>Как соберём подарок?</h2>
    <div className={styles.modeCards}>
      <button type="button" className={styles.modeCard} aria-label="С анимацией" disabled={!supportsSimple(box)} onClick={() => onSelect("simple")}>
        <span className={styles.modeBadge}><AutoAwesomeOutlined fontSize="small" /></span>
        <BoxArtwork flat />
        <strong>С анимацией</strong><span>{supportsSimple(box) ? "Выбираете — упаковываем." : "Недоступно для этой коробки"}</span>
        <ArrowForwardRounded className={styles.modeArrow} />
      </button>
      <button type="button" className={`${styles.modeCard} ${styles.modeCardAdvanced}`} aria-label="Вручную" onClick={() => onSelect("advanced")}>
        <span className={styles.modeBadge}><ViewInArOutlined fontSize="small" /></span>
        <BoxArtwork />
        <strong>Вручную</strong><span>Каждый предмет на своём месте.</span>
        <ArrowForwardRounded className={styles.modeArrow} />
      </button>
    </div>
  </section>;
}

export function ConstructorBoxPicker({ boxes, selectedId, onSelect, cellSizeMm }: {
  boxes: GiftSizeProfile[]; selectedId: number | null; onSelect: (box: GiftSizeProfile) => void; cellSizeMm?: number | null;
}) {
  return <section className={styles.choiceSection} aria-label="Выбор коробки">
    <h2>Выберите коробку</h2>
    <Suspense fallback={<p role="status">Загружаем модели…</p>}>
      <ConstructorBoxScene boxes={boxes} selectedId={selectedId} onSelect={onSelect} />
    </Suspense>
    <div className={styles.boxCards}>{boxes.map((box) => <button key={box.id} type="button"
      className={styles.boxCard} aria-label={`Выбрать коробку ${box.name}`} aria-pressed={selectedId === box.id} onClick={() => onSelect(box)}>
      <strong>{box.name}</strong>
      <span>{box.width_cells * (cellSizeMm || 1)} × {box.height_cells * (cellSizeMm || 1)} {cellSizeMm ? "мм" : "клеток"}</span>
      {selectedId === box.id && <CheckRounded className={styles.boxChecked} />}
    </button>)}</div>
  </section>;
}
import { lazy, Suspense } from "react";
