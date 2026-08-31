import { useEffect, useRef } from "react";
import type { ConstructorProductSize } from "../../interfaces/giftConstructor";
import { formatFootprint } from "../../utils/constructorInteraction";
import { normalizeAssetUrl } from "../../utils/assetUrl";
import { constructorItemPrice, constructorItemQuantity } from "../../utils/giftConstructor";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

interface Props {
  size: ConstructorProductSize;
  cellSizeMm?: number | null;
  onClose: () => void;
  onAdd: (size: ConstructorProductSize) => void;
  atLimit: boolean;
}

function field(label: string, value: string | number | null | undefined, units = ""): string | null {
  const text = value === null || value === undefined || value === "" ? null : String(value).trim();
  return text ? `${label}: ${text}${units}` : null;
}

export default function ProductDetailsModal({ size, cellSizeMm, onClose, onAdd, atLimit }: Props) {
  const dialog = useRef<HTMLDialogElement>(null);
  const product = size.product;

  useEffect(() => {
    const node = dialog.current;
    if (!node) return;
    if (typeof node.showModal === "function" && !node.open) node.showModal();
    else if (!node.open) node.setAttribute("open", "");
    const previous = document.activeElement as HTMLElement | null;
    return () => { previous?.focus?.(); };
  }, []);

  const lines = [
    field("Состав", product.ingredients),
    field("Вес", product.weight_grams, " г"),
    field("Описание", product.description),
    field("Сборка", product.assembly_instructions),
    field("Бренд", product.brand),
    field("Штрихкод (SKU)", product.sku),
    field("Продано", product.sold_count, " шт"),
    field("Наличие на складе", product.total_quantity, " шт"),
  ].filter((line): line is string => Boolean(line));

  return <dialog ref={dialog} className={styles.productDetailsModal} onCancel={(event) => { event.preventDefault(); onClose(); }}
    onClick={(event) => { if (event.target === dialog.current) onClose(); }}>
    <div className={styles.productDetailsBody}>
      <button type="button" className={styles.productDetailsClose} aria-label="Закрыть подробности" onClick={onClose}>×</button>
      <img src={normalizeAssetUrl(product.image) || "/pages/catalog/details/tea.svg"} alt="" />
      <header>
        <h3>{product.name}</h3>
        {product.brand && <p className={styles.productDetailsBrand}>{product.brand}</p>}
      </header>
      <dl className={styles.productDetailsGrid}>
        <div><dt>Формат</dt><dd>{size.label}</dd></div>
        <div><dt>Кол-во в позиции</dt><dd>{constructorItemQuantity(size)}</dd></div>
        <div><dt>Габариты</dt><dd>{formatFootprint(size.size.width_cells, size.size.height_cells, cellSizeMm)}</dd></div>
        <div><dt>Цена</dt><dd>{constructorItemPrice(size).toLocaleString("ru-RU")} ₽ <small>до скидок</small></dd></div>
      </dl>
      {lines.length > 0 && <ul className={styles.productDetailsFacts}>
        {lines.map((line) => <li key={line}>{line}</li>)}
      </ul>}
      <div className={styles.productDetailsActions}>
        <button type="button" onClick={onClose}>Закрыть</button>
        <button type="button" className={styles.primary} disabled={atLimit} onClick={() => { onAdd(size); onClose(); }}>+ Добавить в коробку</button>
      </div>
    </div>
  </dialog>;
}
