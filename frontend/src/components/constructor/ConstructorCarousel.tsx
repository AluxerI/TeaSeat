import { useEffect, useId, useRef, useState } from "react";
import AddShoppingCartRounded from "@mui/icons-material/AddShoppingCartRounded";
import VisibilityOutlined from "@mui/icons-material/VisibilityOutlined";
import IconButton from "@mui/material/IconButton";
import type { ConstructorProductSize, GiftSizeProfile } from "../../interfaces/giftConstructor";
import type { useConstructorDrag } from "../../hooks/useConstructorDrag";
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

// Широкий каталог: 4 × 3 на десктопе, колонки адаптируются на узких экранах.
const PAGE_SIZE = 12;

type Role = ConstructorProductSize["constructor_role"];
// ProductSize::ROLE_*: это роли конструктора, а не категории обычного каталога.
const roles: { id: Role; label: string }[] = [
  { id: "tea", label: "Чай" }, { id: "sweet", label: "Сладости" }, { id: "general", label: "Другое" },
];
export interface CatalogBrowseState { role: Role; search: string; formatIds: Partial<Record<Role, number>> }
export const initialCatalogBrowse = (sizes: ConstructorProductSize[]): CatalogBrowseState => ({
  role: roles.find((role) => sizes.some((size) => size.constructor_role === role.id))?.id ?? "tea", search: "", formatIds: {},
});
interface Props {
  box: GiftSizeProfile;
  options: ConstructorProductSize[];
  selected: number[];
  limit: number;
  onAdd: (size: ConstructorProductSize) => void;
  drag: ReturnType<typeof useConstructorDrag>;
  cellSizeMm?: number | null;
  browse: CatalogBrowseState;
  onBrowse: (state: CatalogBrowseState) => void;
}

export default function ConstructorCarousel({ box, options, selected, limit, onAdd, drag, cellSizeMm, browse, onBrowse }: Props) {
  const id = useId();
  const tabsRef = useRef<(HTMLButtonElement | null)[]>([]);
  const gridRef = useRef<HTMLDivElement>(null);
  const [details, setDetails] = useState<ConstructorProductSize | null>(null);
  const { role, search, formatIds } = browse;
  const filtered = options.filter((size) => size.constructor_role === role
    && `${size.product.name} ${size.label}`.toLocaleLowerCase("ru-RU").includes(search.trim().toLocaleLowerCase("ru-RU")));
  const index = Math.max(0, filtered.findIndex((size) => size.id === formatIds[role]));
  const pageCount = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const pageIndex = Math.min(Math.floor(index / PAGE_SIZE), pageCount - 1);
  const pages = Array.from({ length: pageCount }, (_, p) => filtered.slice(p * PAGE_SIZE, p * PAGE_SIZE + PAGE_SIZE));
  const atLimit = selected.length >= limit;
  const selectRole = (next: Role) => onBrowse({ ...browse, role: next, search: "" });
  const selectIndex = (next: number) => {
    const target = filtered[Math.max(0, Math.min(filtered.length - 1, next))];
    if (target) onBrowse({ ...browse, formatIds: { ...formatIds, [role]: target.id } });
  };
  const reveal = (page: number, behavior: ScrollBehavior = "smooth") => {
    const node = gridRef.current;
    const target = node?.children[Math.max(0, Math.min(pageCount - 1, page))] as HTMLElement | undefined;
    const first = node?.firstElementChild as HTMLElement | null;
    if (node && target && first && typeof node.scrollTo === "function") node.scrollTo({ left: target.offsetLeft - first.offsetLeft, behavior });
  };
  const shiftPage = (delta: number) => {
    const page = Math.max(0, Math.min(pageCount - 1, pageIndex + delta));
    reveal(page);
    selectIndex(Math.max(0, Math.min(filtered.length - 1, page * PAGE_SIZE)));
  };
  // При смене вкладки или выбранного формата листаем к нужной странице сетки.
  useEffect(() => {
    reveal(pageIndex, "auto");
  }, [role, formatIds[role], filtered.length]);
  // Кнопки внутри карточки сохраняют обычное поведение: их нельзя случайно потянуть.
  const interactive = (target: EventTarget) => target instanceof Element && Boolean(target.closest("button, input, a, select, textarea"));
  const footprint = (size: ConstructorProductSize) => {
    const isRotated = size.size.can_rotate && (size.size.width_cells > box.width_cells || size.size.height_cells > box.height_cells);
    const width = isRotated ? size.size.height_cells : size.size.width_cells;
    const height = isRotated ? size.size.width_cells : size.size.height_cells;
    return { width, height };
  };
  const from = pageIndex * PAGE_SIZE + 1;
  const to = Math.min(pageIndex * PAGE_SIZE + PAGE_SIZE, filtered.length);

  return <section className={`${styles.catalog} ${styles.carouselCatalog}`} aria-label="Каталог форматов">
    <h2>Товары <small aria-label="Заполнение коробки">{selected.length} / {limit}</small></h2>
    <div className={styles.catalogTabs} role="tablist" aria-label="Тип товара">
      {roles.map((group, tabIndex) => <button key={group.id} ref={(node) => { tabsRef.current[tabIndex] = node; }} type="button" role="tab"
        id={`${id}-${group.id}`} aria-controls={`${id}-panel-${group.id}`} aria-selected={role === group.id} tabIndex={role === group.id ? 0 : -1}
        disabled={drag.dragging} onClick={() => selectRole(group.id)} onKeyDown={(event) => {
          const next = event.key === "ArrowRight" ? (tabIndex + 1) % roles.length : event.key === "ArrowLeft" ? (tabIndex + roles.length - 1) % roles.length
            : event.key === "Home" ? 0 : event.key === "End" ? roles.length - 1 : null;
          if (next === null) return;
          event.preventDefault(); selectRole(roles[next].id); tabsRef.current[next]?.focus();
        }}>{group.label} <small>{options.filter((option) => option.constructor_role === group.id).length}</small></button>)}
    </div>
    {roles.map((group) => <div key={group.id} id={`${id}-panel-${group.id}`} role="tabpanel" aria-labelledby={`${id}-${group.id}`} hidden={group.id !== role}>
    {group.id === role && <>
      <div className={styles.catalogTools}>
        <input type="search" placeholder="Найти товар" aria-label="Найти товар или формат" value={search} disabled={drag.dragging} onChange={(event) => onBrowse({ ...browse, search: event.target.value })} />
      </div>
      {!filtered.length ? <p role="status">{search ? "В этом типе ничего не найдено. Очистите поиск или выберите другую вкладку." : "В этом типе пока нет доступных форматов. Выберите другую вкладку."}</p>
        : <section aria-roledescription="карусель" aria-label={`Форматы: ${group.label}`}>
          <div className={styles.carouselViewport}>
            <button type="button" className={`${styles.carouselArrow} ${styles.carouselArrowLeft}`} disabled={pageIndex === 0 || drag.dragging} aria-label="Предыдущая страница форматов" onClick={() => shiftPage(-1)}><span aria-hidden="true">‹</span></button>
            <span className={styles.carouselCounter} role="status">Форматы {from}–{to} из {filtered.length}</span>
            <button type="button" className={`${styles.carouselArrow} ${styles.carouselArrowRight}`} disabled={pageIndex >= pageCount - 1 || drag.dragging} aria-label="Следующая страница форматов" onClick={() => shiftPage(1)}><span aria-hidden="true">›</span></button>
            <div ref={gridRef} className={styles.carouselGrid} role="grid" tabIndex={0} aria-label="Сетка форматов" onKeyDown={(event) => {
              if (event.target !== event.currentTarget || drag.dragging) return;
              if (event.key === "ArrowRight") { event.preventDefault(); shiftPage(1); }
              else if (event.key === "ArrowLeft") { event.preventDefault(); shiftPage(-1); }
              else if (event.key === "Home") { event.preventDefault(); shiftPage(-pageIndex); }
              else if (event.key === "End") { event.preventDefault(); shiftPage(pageCount - 1 - pageIndex); }
            }}>
              {pages.map((groupItems, p) => (
                <div key={p} className={styles.carouselPage} aria-label={`Страница ${p + 1}`} data-active={p === pageIndex} inert={p !== pageIndex} aria-hidden={p !== pageIndex}>
                  {groupItems.map((size) => {
                    const fp = footprint(size);
                    const active = size.id === filtered[index]?.id;
                    const remainingStock = constructorRemainingStock(size, selected, options);
                    const hasStock = constructorHasStock(size, selected, options);
                    return <article key={size.id} className={`${styles.carouselCard}${active ? ` ${styles.carouselActive}` : ""}`}
                      aria-roledescription="слайд" aria-label={`${size.product.name}, ${size.label}`} tabIndex={active ? 0 : -1} data-active={active}
                      data-unavailable={!hasStock}
                      data-dragging={drag.dragging && drag.cursor?.placement.product_size_id === size.id}
                      onFocus={() => selectIndex(filtered.findIndex((candidate) => candidate.id === size.id))} onPointerDown={(event) => { if (!atLimit && hasStock && !interactive(event.target)) drag.startCatalog(size, event); }}
                      onTouchStart={(event) => { if (!interactive(event.target)) drag.startCatalogTouch(size, event, shiftPage, !atLimit && hasStock); }}
                      onContextMenu={drag.preventTouchMenu} onClickCapture={drag.allowCatalogClick}>
                      <img className={styles.carouselImage} src={normalizeAssetUrl(size.product.image) || "/pages/catalog/details/tea.svg"} alt="" loading="lazy" draggable={false} />
                      <div className={styles.productDescription}>
                        <strong>{size.product.name}</strong>
                        <span>{size.label}</span>
                        <span>{constructorItemPrice(size).toLocaleString("ru-RU")} ₽</span>
                        <span className={styles.stock} data-stock-empty={!hasStock}>Доступно: {constructorStockLabel(size, remainingStock)}</span>
                      </div>
                      <div className={styles.cardActions}>
                        <FootprintDiagram compact box={box} width={fp.width} height={fp.height} cellSizeMm={cellSizeMm} />
                        <output data-empty={!selected.includes(size.id)} aria-label={`Количество ${size.product.name}, ${size.label}`}>{selected.filter((item) => item === size.id).length}</output>
                        <div className={styles.cardButtons}>
                          <IconButton className={styles.detailsButton} disabled={drag.dragging} title="Подробнее" aria-label={`Подробнее о ${size.product.name}`} onClick={() => setDetails(size)}><VisibilityOutlined fontSize="small" /></IconButton>
                          <IconButton className={styles.addButton} disabled={atLimit || drag.dragging || !hasStock} title={hasStock ? "Добавить в коробку" : "Недостаточно товара"} aria-label={`Добавить ${size.product.name}, ${size.label}`} onClick={() => onAdd(size)}><AddShoppingCartRounded fontSize="small" /></IconButton>
                        </div>
                      </div>
                    </article>;
                  })}
                </div>
              ))}
            </div>
          </div>
          {pageCount > 1 && <p className={styles.pageIndicator} role="status">Страница {pageIndex + 1} из {pageCount}</p>}
          {details && <ProductDetailsModal box={box} size={details} cellSizeMm={cellSizeMm} onClose={() => setDetails(null)} onAdd={onAdd} atLimit={atLimit}
            remainingStock={constructorRemainingStock(details, selected, options)} />}
        </section>}
      {atLimit && <p role="status">Достигнут лимит {limit} позиций. Удалите позицию, чтобы добавить другую.</p>}
    </>}
    </div>)}
  </section>;
}
