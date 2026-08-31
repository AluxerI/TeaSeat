import { useState } from "react";
import type { ConstructorProductSize } from "../../interfaces/giftConstructor";
import { formatFootprint } from "../../utils/constructorInteraction";
import { normalizeAssetUrl } from "../../utils/assetUrl";
import { constructorItemPrice, constructorItemQuantity } from "../../utils/giftConstructor";
import ProductDetailsModal from "./ProductDetailsModal";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

const PAGE_SIZE = 20;

interface Props {
  title: string;
  options: ConstructorProductSize[];
  selected: number[];
  limit: number;
  onAdd: (size: ConstructorProductSize) => void;
  onRemove?: (size: ConstructorProductSize) => void;
  cellSizeMm?: number | null;
}

/** Простой режим остаётся списком с пагинацией по карточкам. */
export default function ConstructorCatalog({ title, options, selected, limit, onAdd, onRemove, cellSizeMm }: Props) {
  const [page, setPage] = useState(0);
  const [details, setDetails] = useState<ConstructorProductSize | null>(null);
  const pages = Math.max(1, Math.ceil(options.length / PAGE_SIZE));
  const current = Math.min(page, pages - 1);
  const pageItems = options.slice(current * PAGE_SIZE, current * PAGE_SIZE + PAGE_SIZE);

  return <section className={styles.catalog}>
    <h2>{title} <small>{selected.length} выбрано · максимум {limit}</small></h2>
    {!options.length && <p role="status">Список форматов для этого раздела пуст.</p>}
    <ul className={styles.productList}>
      {pageItems.map((size) => {
        const count = selected.filter((id) => id === size.id).length;
        return <li key={size.id}>
          <img src={normalizeAssetUrl(size.product.image) || "/pages/catalog/details/tea.svg"} alt="" loading="lazy" draggable={false} />
          <div className={styles.productDescription}>
            <strong>{size.product.name}</strong>
            <span>{size.label} · {constructorItemQuantity(size)}</span>
            <span>{formatFootprint(size.size.width_cells, size.size.height_cells, cellSizeMm)}</span>
            <span>{constructorItemPrice(size).toLocaleString("ru-RU")} ₽ <small>до скидок</small></span>
          </div>
          <div className={styles.counter}>
            {onRemove && <button type="button" disabled={!count} aria-label={`Убрать ${size.product.name}, ${size.label}`} onClick={() => onRemove(size)}>−</button>}
            <output aria-label={`Количество ${size.product.name}, ${size.label}`}>{count}</output>
            <button type="button" disabled={selected.length >= limit} aria-label={`Добавить ${size.product.name}, ${size.label}`} onClick={() => onAdd(size)}>+</button>
          </div>
          <button type="button" className={styles.detailsButton} aria-label={`Подробнее о ${size.product.name}`} onClick={() => setDetails(size)}>Подробнее</button>
        </li>;
      })}
    </ul>
    {options.length > PAGE_SIZE && <nav className={styles.pagination} aria-label="Пагинация каталога">
      <button type="button" disabled={current === 0} onClick={() => setPage(current - 1)}>← Назад</button>
      <span role="status">Страница {current + 1} из {pages} · товаров {options.length}</span>
      <button type="button" disabled={current === pages - 1} onClick={() => setPage(current + 1)}>Далее →</button>
    </nav>}
    {details && <ProductDetailsModal size={details} cellSizeMm={cellSizeMm} onClose={() => setDetails(null)} onAdd={onAdd} atLimit={selected.length >= limit} />}
  </section>;
}
