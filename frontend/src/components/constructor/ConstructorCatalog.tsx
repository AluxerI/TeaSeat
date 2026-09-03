import { Fragment, useState } from "react";
import AddShoppingCartRounded from "@mui/icons-material/AddShoppingCartRounded";
import VisibilityOutlined from "@mui/icons-material/VisibilityOutlined";
import RemoveRounded from "@mui/icons-material/RemoveRounded";
import IconButton from "@mui/material/IconButton";
import type { ConstructorProductSize, GiftSizeProfile } from "../../interfaces/giftConstructor";
import { normalizeAssetUrl } from "../../utils/assetUrl";
import {
  constructorHasStock,
  constructorItemPrice,
  constructorRemainingStock,
  constructorStockLabel,
} from "../../utils/giftConstructor";
import ProductDetailsModal from "./ProductDetailsModal";
import FootprintDiagram from "./FootprintDiagram";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

const PAGE_SIZE = 20;

interface Props {
  title: string;
  box: GiftSizeProfile;
  options: ConstructorProductSize[];
  selected: number[];
  stockSelected?: number[];
  stockOptions?: ConstructorProductSize[];
  limit: number;
  onAdd: (size: ConstructorProductSize) => void;
  onRemove?: (size: ConstructorProductSize) => void;
  cellSizeMm?: number | null;
}

/** Простой режим остаётся списком с пагинацией по карточкам. */
export default function ConstructorCatalog({ title, box, options, selected, stockSelected = selected, stockOptions = options, limit, onAdd, onRemove, cellSizeMm }: Props) {
  const [page, setPage] = useState(0);
  const [details, setDetails] = useState<ConstructorProductSize | null>(null);
  const pages = Math.max(1, Math.ceil(options.length / PAGE_SIZE));
  const current = Math.min(page, pages - 1);
  const pageItems = options.slice(current * PAGE_SIZE, current * PAGE_SIZE + PAGE_SIZE);
  const visiblePages = [...new Set([0, current - 1, current, current + 1, pages - 1])]
    .filter((index) => index >= 0 && index < pages).sort((a, b) => a - b);

  return <section className={styles.catalog}>
    <h2>{title} <small>{selected.length} / {limit}</small></h2>
    {!options.length && <p role="status">Список форматов для этого раздела пуст.</p>}
    <ul className={styles.productList}>
      {pageItems.map((size) => {
        const count = selected.filter((id) => id === size.id).length;
        const remainingStock = constructorRemainingStock(size, stockSelected, stockOptions);
        const hasStock = constructorHasStock(size, stockSelected, stockOptions);
        return <li key={size.id} data-unavailable={!hasStock}>
          <img src={normalizeAssetUrl(size.product.image) || "/pages/catalog/details/tea.svg"} alt="" loading="lazy" draggable={false} />
          <div className={styles.productDescription}>
            <strong>{size.product.name}</strong>
            <span>{size.label}</span>
            <span>{constructorItemPrice(size).toLocaleString("ru-RU")} ₽</span>
            <span className={styles.stock} data-stock-empty={!hasStock}>Доступно: {constructorStockLabel(size, remainingStock)}</span>
          </div>
          <FootprintDiagram compact box={box} width={size.size.width_cells} height={size.size.height_cells} cellSizeMm={cellSizeMm} className={styles.listFootprint} />
          <div className={styles.counter}>
            {onRemove && <IconButton disabled={!count} title="Убрать" aria-label={`Убрать ${size.product.name}, ${size.label}`} onClick={() => onRemove(size)}><RemoveRounded fontSize="small" /></IconButton>}
            <output aria-label={`Количество ${size.product.name}, ${size.label}`}>{count}</output>
            <IconButton className={styles.addButton} disabled={selected.length >= limit || !hasStock} title={hasStock ? "Добавить в коробку" : "Недостаточно товара"} aria-label={`Добавить ${size.product.name}, ${size.label}`} onClick={() => onAdd(size)}><AddShoppingCartRounded fontSize="small" /></IconButton>
            <IconButton className={styles.detailsButton} title="Подробнее" aria-label={`Подробнее о ${size.product.name}`} onClick={() => setDetails(size)}><VisibilityOutlined fontSize="small" /></IconButton>
          </div>
        </li>;
      })}
    </ul>
    {options.length > PAGE_SIZE && <nav className={styles.pageSelector} aria-label="Страницы каталога">
      {visiblePages.map((pageIndex, index) => <Fragment key={pageIndex}>
        {index > 0 && pageIndex - visiblePages[index - 1] > 1 && <span aria-hidden="true">…</span>}
        <button type="button" aria-label={`Страница ${pageIndex + 1}`} aria-current={current === pageIndex ? "page" : undefined} onClick={() => setPage(pageIndex)}>{pageIndex + 1}</button>
      </Fragment>)}
      <span className={styles.pageTotal} role="status">из {pages}</span>
    </nav>}
    {details && <ProductDetailsModal box={box} size={details} cellSizeMm={cellSizeMm} onClose={() => setDetails(null)} onAdd={onAdd} atLimit={selected.length >= limit}
      remainingStock={constructorRemainingStock(details, stockSelected, stockOptions)} />}
  </section>;
}
