import { lazy, Suspense, useMemo, useState } from "react";
import type { ConstructorDraft, ConstructorProductSize, GiftSizeProfile, LayoutPlacement } from "../../interfaces/giftConstructor";
import ConstructorCatalog from "./ConstructorCatalog";
import GiftConfirmation from "./GiftConfirmation";
import FloorGrid from "./FloorGrid";
import { createGiftInstanceId } from "../../utils/constructorErrors";
import { firstFreePlacement, footprint, layoutError, MAX_LAYOUT_ITEMS } from "../../utils/giftLayout";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

// Импортирован только в сложном режиме: простой список не загружает Three.js.
const BoxFloorScene = lazy(() => import("./BoxFloorScene"));

interface Props {
  mode: "simple" | "advanced";
  box: GiftSizeProfile;
  sizes: ConstructorProductSize[];
  onBusy: (busy: boolean) => void;
}

export default function ConstructorEditor({ mode, box, sizes, onBusy }: Props) {
  const [teas, setTeas] = useState<number[]>([]);
  const [sweets, setSweets] = useState<number[]>([]);
  const [items, setItems] = useState<LayoutPlacement[]>([]);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [error, setError] = useState("");
  const [confirming, setConfirming] = useState(false);
  const [placementStarted, setPlacementStarted] = useState(false);
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
    ? Boolean(requirements && teas.length === requirements.tea_count && sweets.length === requirements.sweet_count && contents.length === selectedIds.length)
    : items.length > 0 && !layoutError(box, sizes, items);

  const reset = () => {
    setConfirming(false); setTeas([]); setSweets([]); setItems([]); setSelectedId(null); setError("");
  };

  function changeItem(id: string, changes: Partial<LayoutPlacement>) {
    const next = items.map((item) => item.client_item_id === id ? { ...item, ...changes } : item);
    const problem = layoutError(box, sizes, next);
    if (problem) { setError(problem); return; }
    setError(""); setItems(next);
  }

  function addAdvanced(size: ConstructorProductSize) {
    const placement = firstFreePlacement(box, sizes, items, size, createGiftInstanceId());
    if (!placement) { setError("Нет места для этого формата. Переместите или удалите позиции."); return; }
    setItems([...items, placement]); setSelectedId(placement.client_item_id); setError("");
  }

  const removeOne = (list: number[], id: number) => {
    const index = list.lastIndexOf(id);
    return list.filter((_, current) => current !== index);
  };
  const floorProps = {
    box, sizes, items, selectedId,
    onSelect: setSelectedId,
    onCell: (x: number, y: number) => {
      if (!selectedId) { setError("Сначала выберите позицию в коробке или добавьте товар."); return; }
      changeItem(selectedId, { position_x: x, position_y: y });
    },
  };

  if (confirming) return <GiftConfirmation draft={draft} box={box} contents={contents} onBack={() => setConfirming(false)} onReset={reset} onBusy={onBusy} />;

  return <>
    {mode === "simple" ? <>
      <p className={styles.hint}>Выберите состав. Раскладку выполнит сервер. Коробка и упаковка: {box.default_markup_amount} ₽ до расчёта.</p>
      <div className={styles.simpleColumns}>
        <ConstructorCatalog title="Чай" options={teaSizes} selected={teas} limit={requirements?.tea_count ?? 0}
          onAdd={(size) => setTeas((prev) => prev.length < (requirements?.tea_count ?? 0) ? [...prev, size.id] : prev)}
          onRemove={(size) => setTeas((prev) => removeOne(prev, size.id))} />
        <ConstructorCatalog title="Сладости" options={sweetSizes} selected={sweets} limit={requirements?.sweet_count ?? 0}
          onAdd={(size) => setSweets((prev) => prev.length < (requirements?.sweet_count ?? 0) ? [...prev, size.id] : prev)}
          onRemove={(size) => setSweets((prev) => removeOne(prev, size.id))} />
      </div>
    </> : <div className={styles.advancedColumns}>
      <section className={`${styles.floorPanel} ${!placementStarted ? styles.floorPreview : ""}`}>
        <h2>{placementStarted ? "Дно коробки" : box.name} · {box.width_cells} × {box.height_cells}</h2>
        <p className={styles.hint}>{placementStarted
          ? "Добавьте товар. Выберите позицию и нажмите свободную ячейку для переноса её верхнего левого угла."
          : "Выберите коробку в списке выше и подтвердите выбор. Камера перейдёт к её дну, где можно расставить товары."}</p>
        <Suspense fallback={placementStarted ? <FloorGrid {...floorProps} /> : <p role="status">Загружаем модель коробки…</p>}>
          <BoxFloorScene {...floorProps} editing={placementStarted} onChoose={() => setPlacementStarted(true)} />
        </Suspense>
        {!placementStarted && <button type="button" className={styles.primary} onClick={() => setPlacementStarted(true)}>Выбрать коробку и расставить товары</button>}
        <ol className={styles.placedList}>
          {items.map((item) => {
            const size = sizes.find((candidate) => candidate.id === item.product_size_id);
            const [width, height] = size ? footprint(size, item.is_rotated) : [1, 1];
            return <li key={item.client_item_id}>
              <button type="button" aria-pressed={selectedId === item.client_item_id} onClick={() => setSelectedId(item.client_item_id)}>{size?.product.name ?? "Недоступный формат"} · {width} × {height}</button>
              <button type="button" aria-label={`Удалить ${size?.product.name ?? "формат"} из коробки`} onClick={() => {
                setItems(items.filter((candidate) => candidate.client_item_id !== item.client_item_id));
                if (selectedId === item.client_item_id) setSelectedId(null);
                setError("");
              }}>Удалить</button>
            </li>;
          })}
        </ol>
        {selectedItem && selectedSize && <fieldset className={styles.positionControls}>
          <legend>Позиция: {selectedSize.product.name}</legend>
          <label>Столбец<input aria-label="Столбец позиции" type="number" min={1} max={box.width_cells} value={selectedItem.position_x + 1} onChange={(event) => changeItem(selectedItem.client_item_id, { position_x: Number(event.target.value) - 1 })} /></label>
          <label>Строка<input aria-label="Строка позиции" type="number" min={1} max={box.height_cells} value={selectedItem.position_y + 1} onChange={(event) => changeItem(selectedItem.client_item_id, { position_y: Number(event.target.value) - 1 })} /></label>
          <button type="button" disabled={!selectedSize.size.can_rotate} onClick={() => changeItem(selectedItem.client_item_id, { is_rotated: !selectedItem.is_rotated })}>Повернуть на 90°</button>
        </fieldset>}
      </section>
      {placementStarted && <ConstructorCatalog title="Добавить в коробку" options={sizes} selected={selectedIds} limit={MAX_LAYOUT_ITEMS} onAdd={addAdvanced} />}
    </div>}
    {error && <p role="alert" className={styles.error}>{error}</p>}
    {contents.length !== selectedIds.length && <p role="alert">Часть выбранных форматов больше недоступна. Удалите их или очистите выбор.</p>}
    {(mode === "simple" || placementStarted) && <div className={styles.actions}>
      <button type="button" className={styles.primary} disabled={!ready} onClick={() => setConfirming(true)}>Проверить подарок и цену</button>
      <button type="button" disabled={!selectedIds.length} onClick={reset}>Очистить выбор</button>
      <span className={styles.hint}>{contents.length} позиций выбрано</span>
    </div>}
  </>;
}
