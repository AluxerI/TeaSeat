import { lazy, Suspense, useEffect, useId, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import RotateLeftRounded from "@mui/icons-material/RotateLeftRounded";
import RotateRightRounded from "@mui/icons-material/RotateRightRounded";
import DeleteOutlineRounded from "@mui/icons-material/DeleteOutlineRounded";
import IconButton from "@mui/material/IconButton";
import type { ConstructorDraft, ConstructorProductSize, GiftSizeProfile, LayoutPlacement } from "../../interfaces/giftConstructor";
import ConstructorCatalog from "./ConstructorCatalog";
import ConstructorCarousel, { initialCatalogBrowse } from "./ConstructorCarousel";
import GiftConfirmation from "./GiftConfirmation";
import FloorGrid from "./FloorGrid";
import { createGiftInstanceId } from "../../utils/constructorErrors";
import { firstFreePlacement, footprint, layoutError, MAX_LAYOUT_ITEMS } from "../../utils/giftLayout";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";
import ConstructorSteps from "./ConstructorSteps";
import { useConstructorDrag } from "../../hooks/useConstructorDrag";
import ProductTexture from "./ProductTexture";
import { useSimpleGiftQuote } from "../../hooks/useSimpleGiftQuote";
import {
  constructorHasStock,
  constructorItemQuantity,
  constructorRemainingStock,
  constructorStockLabel,
} from "../../utils/giftConstructor";

// Каждый режим загружает свою сцену по требованию.
const BoxFloorScene = lazy(() => import("./BoxFloorScene"));
const SimplePackingScene = lazy(() => import("./SimplePackingScene"));

interface Props {
  mode: "simple" | "advanced";
  box: GiftSizeProfile;
  sizes: ConstructorProductSize[];
  onBusy: (busy: boolean) => void;
  cellSizeMm?: number | null;
  onBackToBoxes?: () => void;
  onBackToMode?: () => void;
  active?: boolean;
  startEditing?: boolean;
}

export default function ConstructorEditor({ mode, box, sizes, onBusy, cellSizeMm, onBackToBoxes, onBackToMode, active = true, startEditing = false }: Props) {
  const [teas, setTeas] = useState<number[]>([]);
  const [sweets, setSweets] = useState<number[]>([]);
  const [items, setItems] = useState<LayoutPlacement[]>([]);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [error, setError] = useState("");
  const [confirming, setConfirming] = useState(false);
  const [placementStarted, setPlacementStarted] = useState(startEditing);
  const animatedItems = useRef(new Set<string>());
  const quoteStatusId = useId();
  const simpleQuote = useSimpleGiftQuote(box, sizes, teas, sweets, mode === "simple" && active && !confirming);
  const [transforming, setTransforming] = useState(false);
  // Навигация каталога живёт столько же, сколько состав: возврат по шагам её не сбрасывает.
  const [browse, setBrowse] = useState(() => initialCatalogBrowse(sizes));
  const [pendingSizeId, setPendingSizeId] = useState<number | null>(null);
  const requirements = box.simple_requirements;
  const selectedItem = items.find((item) => item.client_item_id === selectedId);
  const selectedSize = sizes.find((size) => size.id === selectedItem?.product_size_id);
  const teaSizes = sizes.filter((size) => size.constructor_role === "tea");
  const sweetSizes = sizes.filter((size) => size.constructor_role === "sweet");

  const draft = useMemo<ConstructorDraft>(() => mode === "simple"
    ? { mode, selection: { box_profile_id: box.id, tea_product_size_ids: teas, sweet_product_size_ids: sweets } }
    : { mode, selection: { box_profile_id: box.id, items } }, [box.id, mode, teas, sweets, items]);
  const selectedIds = mode === "simple" ? [...teas, ...sweets] : items.map((item) => item.product_size_id);
  const contents = selectedIds.map((id) => sizes.find((size) => size.id === id)).filter((size): size is ConstructorProductSize => Boolean(size));
  const ready = mode === "simple"
    ? Boolean(simpleQuote.approved)
    : items.length > 0 && !layoutError(box, sizes, items);
  const drag = useConstructorDrag({ box, sizes, items, onSelect: setSelectedId, onError: setError,
    onCommit: (placement, existing) => {
      const size = sizes.find((candidate) => candidate.id === placement.product_size_id);
      const stockProblem = !existing && size ? stockError(size) : null;
      if (stockProblem) { setError(stockProblem); return; }
      const next = existing ? items.map((item) => item.client_item_id === placement.client_item_id ? placement : item) : [...items, placement];
      const problem = layoutError(box, sizes, next);
      if (problem) { setError(problem); return; }
      setItems(next); setSelectedId(placement.client_item_id); setError("");
    },
  });

  const reset = () => {
    if (mode === "simple") { animatedItems.current.clear(); setTeas([]); setSweets([]); }
    else { setItems([]); setSelectedId(null); }
    setConfirming(false); setError("");
  };
  // Возврат к размеру/режиму хранит оба черновика, но не скрытый WebGL-контекст.
  useEffect(() => { if (!active) setConfirming(false); }, [active]);

  function changeItem(id: string, changes: Partial<LayoutPlacement>) {
    const next = items.map((item) => item.client_item_id === id ? { ...item, ...changes } : item);
    const problem = layoutError(box, sizes, next);
    if (problem) { setError(problem); return; }
    setError(""); setItems(next);
  }

  function addAdvanced(size: ConstructorProductSize) {
    const stockProblem = stockError(size);
    if (stockProblem) { setError(stockProblem); return; }
    const placement = firstFreePlacement(box, sizes, items, size, createGiftInstanceId());
    if (!placement) { setError("Нет места для этого формата. Переместите или удалите позиции."); return; }
    setItems([...items, placement]); setSelectedId(placement.client_item_id); setError("");
  }

  const locked = drag.dragging || transforming;
  function rotateSelected() {
    if (!selectedItem || !selectedSize?.size.can_rotate || locked) return;
    // API хранит две ориентации прямоугольного следа. И +90°, и −90° меняют стороны местами.
    changeItem(selectedItem.client_item_id, { is_rotated: !selectedItem.is_rotated });
  }
  const cursorSize = sizes.find((size) => size.id === drag.cursor?.placement.product_size_id);
  const cursorFootprint = cursorSize ? footprint(cursorSize, drag.cursor!.placement.is_rotated) : null;
  const surface = drag.surfaceRef.current?.getBoundingClientRect();
  const cursorOverFloor = drag.cursor && surface && drag.cursor.clientX >= surface.left && drag.cursor.clientX <= surface.right
    && drag.cursor.clientY >= surface.top && drag.cursor.clientY <= surface.bottom;
  const cursorWidth = cursorFootprint ? Math.min(140, Math.max(48, cursorFootprint[0] * (surface?.width ?? 160) / box.width_cells)) : 72;
  const removeItem = (id: string) => {
    setItems((current) => current.filter((item) => item.client_item_id !== id));
    if (selectedId === id) setSelectedId(null);
    setError("");
  };

  const removeOne = (list: number[], id: number) => {
    const index = list.lastIndexOf(id);
    return list.filter((_, current) => current !== index);
  };
  const addSimple = (size: ConstructorProductSize, role: "tea" | "sweet") => {
    const stockProblem = stockError(size);
    if (stockProblem) { setError(stockProblem); return; }
    if (role === "tea") {
      setTeas((current) => current.length < (requirements?.tea_count ?? 0) ? [...current, size.id] : current);
    } else {
      setSweets((current) => current.length < (requirements?.sweet_count ?? 0) ? [...current, size.id] : current);
    }
    setError("");
  };
  function stockError(size: ConstructorProductSize): string | null {
    if (constructorHasStock(size, selectedIds, sizes)) return null;
    const remaining = constructorRemainingStock(size, selectedIds, sizes);
    return `Недостаточно товара «${size.product.name}»: для позиции нужно ${constructorItemQuantity(size)}, доступно ${constructorStockLabel(size, remaining)}.`;
  }
  const floorProps = {
    box, sizes, items, selectedId, drag, cellSizeMm,
    onSelect: (id: string | null) => { setPendingSizeId(null); setSelectedId(id); },
    onRotate: (id: string, rotated: boolean) => {
      const item = items.find((candidate) => candidate.client_item_id === id);
      if (sizes.find((size) => size.id === item?.product_size_id)?.size.can_rotate) changeItem(id, { is_rotated: rotated });
    },
    onDelete: removeItem,
    onTransformBusy: setTransforming,
    onCell: (x: number, y: number) => {
      const pendingSize = sizes.find((size) => size.id === pendingSizeId);
      if (pendingSize) {
        const stockProblem = stockError(pendingSize);
        if (stockProblem) { setError(stockProblem); setPendingSizeId(null); return; }
        const placement: LayoutPlacement = {
          client_item_id: createGiftInstanceId(), product_size_id: pendingSize.id,
          position_x: x, position_y: y, is_rotated: false,
        };
        const message = layoutError(box, sizes, [...items, placement]);
        if (message) { setError(message); return; }
        setItems([...items, placement]);
        setSelectedId(placement.client_item_id);
        setPendingSizeId(null);
        setError("");
        return;
      }
      if (!selectedId) { setError("Сначала выберите позицию в коробке или добавьте товар."); return; }
      changeItem(selectedId, { position_x: x, position_y: y });
    },
  };

  if (confirming) return <GiftConfirmation draft={draft} box={box} contents={contents} onBack={() => setConfirming(false)}
    onBackToMode={onBackToMode ? () => { setConfirming(false); onBackToMode(); } : undefined}
    onBackToBox={() => { setConfirming(false); if (onBackToBoxes) onBackToBoxes(); else setPlacementStarted(false); }} onReset={reset} onBusy={onBusy} />;

  return <>
    <ConstructorSteps current={2}
      onBack={(step) => { if (step === 0 && onBackToBoxes) onBackToBoxes(); else if (step === 1 && onBackToMode) onBackToMode(); else setPlacementStarted(false); }} locked={locked} />
    {mode === "simple" ? <>
      <div className={styles.simpleWorkspace}>
      {active && <section className={styles.floorPanel} aria-label="Упаковка">
        <Suspense fallback={<p role="status">Загружаем анимацию…</p>}>
          <SimplePackingScene box={box} teas={teas} sweets={sweets} seen={animatedItems} layout={simpleQuote.approved?.layout} />
        </Suspense>
      </section>}
      <div className={styles.simpleColumns}>
        <ConstructorCatalog title="Чай" box={box} options={teaSizes} selected={teas} stockSelected={selectedIds} stockOptions={sizes} limit={requirements?.tea_count ?? 0} cellSizeMm={cellSizeMm}
          onAdd={(size) => addSimple(size, "tea")}
          onRemove={(size) => setTeas((prev) => removeOne(prev, size.id))} />
        <ConstructorCatalog title="Сладости" box={box} options={sweetSizes} selected={sweets} stockSelected={selectedIds} stockOptions={sizes} limit={requirements?.sweet_count ?? 0} cellSizeMm={cellSizeMm}
          onAdd={(size) => addSimple(size, "sweet")}
          onRemove={(size) => setSweets((prev) => removeOne(prev, size.id))} />
      </div>
      </div>
    </> : <div className={styles.advancedColumns}>
      <section className={`${styles.floorPanel} ${!placementStarted ? styles.floorPreview : ""}`}>
        <div className={styles.floorHeading}><h2>{box.name}</h2>
          {placementStarted && selectedItem && selectedSize && <div className={styles.rotationToolbar} role="group" aria-label={`Поворот: ${selectedSize.product.name}`}>
            <IconButton disabled={!selectedSize.size.can_rotate || locked} title="Повернуть влево" aria-label="Повернуть влево на 90°" onClick={rotateSelected}><RotateLeftRounded fontSize="small" /></IconButton>
            <IconButton disabled={!selectedSize.size.can_rotate || locked} title="Повернуть вправо" aria-label="Повернуть вправо на 90°" onClick={rotateSelected}><RotateRightRounded fontSize="small" /></IconButton>
            <IconButton disabled={locked} title="Удалить товар" aria-label={`Удалить ${selectedSize.product.name} из коробки`} onClick={() => removeItem(selectedItem.client_item_id)}><DeleteOutlineRounded fontSize="small" /></IconButton>
          </div>}
        </div>
        {active && <Suspense fallback={placementStarted ? <FloorGrid {...floorProps} /> : <p role="status">Загружаем модель коробки…</p>}>
          <BoxFloorScene {...floorProps} editing={placementStarted} controlsLocked={locked} onChoose={() => setPlacementStarted(true)} />
        </Suspense>}
        {!placementStarted && <button type="button" className={styles.primary} aria-label="Выбрать коробку и расставить товары" onClick={() => setPlacementStarted(true)}>Расставить товары</button>}
        {placementStarted && <p role="status" className={styles.srOnly}>{drag.dragging ? drag.preview?.message ?? "Перенесите предмет на дно коробки" : "Выделите предмет. Стрелки — перемещение, R — поворот, Delete — удаление."}</p>}
      </section>
      {placementStarted && <div data-constructor-pick-mode={pendingSizeId ? "active" : undefined} onClick={(event) => {
        if (locked) return;
        const target = event.target as HTMLElement;
        if (target.closest("button, input, a, select, textarea")) return;
        const card = target.closest('article[aria-label]');
        const label = card?.getAttribute("aria-label");
        if (!label) return;
        const size = sizes.find((candidate) => `${candidate.product.name}, ${candidate.label}` === label);
        if (!size) return;
        const stockProblem = stockError(size);
        if (stockProblem) { setError(stockProblem); return; }
        setPendingSizeId(size.id);
        setSelectedId(null);
        setError("");
      }}>
        <ConstructorCarousel box={box} options={sizes} selected={selectedIds} limit={MAX_LAYOUT_ITEMS} onAdd={addAdvanced} drag={{ ...drag, dragging: locked }} cellSizeMm={cellSizeMm} browse={browse} onBrowse={setBrowse} />
        {pendingSizeId && <p role="status" className={styles.hint}>Товар выбран. Теперь нажмите на свободную ячейку коробки. Перетаскивание по‑прежнему работает.</p>}
      </div>}
    </div>}
    {error && <p role="alert" className={styles.error}>{error}</p>}
    {mode === "advanced" && contents.length !== selectedIds.length && <p role="alert">Часть выбранных форматов больше недоступна. Удалите их или очистите выбор.</p>}
    {mode === "simple" && <div id={quoteStatusId} className={styles.preflight} aria-live="polite">
      {simpleQuote.error ? <p role="alert" className={styles.error}>{contents.length !== selectedIds.length
        ? "Часть выбранных форматов больше недоступна. Удалите их или очистите выбор." : simpleQuote.error}</p>
        : simpleQuote.loading ? <p role="status">Проверяем раскладку, вес и наличие…</p>
        : simpleQuote.approved ? <p role="status">{new Intl.NumberFormat("ru-RU", { style: "currency", currency: "RUB" }).format(simpleQuote.approved.quote.totals.final_total)}</p>
        : <p className={styles.hint}>Чай {teas.length}/{requirements?.tea_count ?? 0} · Сладости {sweets.length}/{requirements?.sweet_count ?? 0}</p>}
      {simpleQuote.complete && !simpleQuote.loading && simpleQuote.error && !simpleQuote.localError && simpleQuote.online
        && <button type="button" onClick={simpleQuote.retry}>Повторить проверку</button>}
    </div>}
    {(mode === "simple" || placementStarted) && <div className={styles.actions}>
      <button type="button" className={styles.primary} disabled={!ready || locked} aria-describedby={mode === "simple" ? quoteStatusId : undefined} onClick={() => { if (ready) setConfirming(true); }}>К оформлению</button>
      <button type="button" disabled={!selectedIds.length || locked} onClick={reset}>Очистить выбор</button>
    </div>}
    {drag.cursor && cursorSize && cursorFootprint && !cursorOverFloor && createPortal(<div className={styles.dragCursor} aria-hidden="true" style={{
      left: drag.cursor.clientX + 8, top: drag.cursor.clientY + 8,
      width: cursorWidth, height: cursorWidth * cursorFootprint[1] / cursorFootprint[0],
    }}><ProductTexture size={cursorSize} rotated={drag.cursor.placement.is_rotated} /></div>, document.body)}
  </>;
}
