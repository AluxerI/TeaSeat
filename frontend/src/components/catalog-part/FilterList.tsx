import { useState } from "react";

// ---------- Типы данных для фильтров ----------
// Каждая секция фильтра (категории, цена, бренд, рейтинг) описывается своим типом

export interface CategoryTreeNode {
  id: string;    // уникальный ключ ("cat_3", "sub_12", "subsub_45")
  label: string; // человекопонятное название ("Чай")
  count: number; // сколько товаров в этой категории/подкатегории
  children?: CategoryTreeNode[];
}

export interface BrandProp {
  id: string;
  label: string;
}

export interface PriceRangeProp {
  id: string;
  label: string;
}

// Текущее состояние всех фильтров.
// Хранится в родителе (Catalog.tsx) и прокидывается сюда через props.
// Это подход controlled component — FilterPanel только показывает состояние
// и дёргает onChange, а кто-то сверху решает, что делать.
export interface FilterState {
  categories: string[];  // массив id выбранных категорий
  priceFrom: string;     // нижняя граница цены (строка, т.к. из input)
  priceTo: string;       // верхняя граница цены
  priceRange: string | null; // id выбранного диапазона ("0-500") или null
  brands: string[];      // массив id выбранных брендов
  rating: number | null; // выбранный рейтинг (5 или 4) или null
}

interface FilterPanelProps {
  filters: FilterState;           // текущее состояние
  onChange?: (filters: FilterState) => void; // сообщить об изменении
  onReset?: () => void;           // дополнительный колбэк при сбросе
  categories?: CategoryTreeNode[]; // дерево категорий (из Catalog)
  brands?: BrandProp[];           // динамические бренды (из Catalog)
}

// ---------- Статические данные для фильтров ----------
// Они жёстко зашиты, но в будущем могут приходить с бэкенда

export const CATEGORIES: CategoryTreeNode[] = [
  { id: "tea", label: "Чай", count: 156 },
  { id: "coffee", label: "Кофе", count: 89 },
  { id: "desserts", label: "Десерты", count: 67 },
  { id: "gift_sets", label: "Подарочные наборы", count: 23 },
  { id: "accessories", label: "Аксессуары", count: 45 },
];

export const PRICE_RANGES: PriceRangeProp[] = [
  { id: "0-500", label: "До 500 ₽" },
  { id: "500-1500", label: "500 – 1 500 ₽" },
  { id: "1500-3000", label: "1 500 – 3 000 ₽" },
  { id: "3000+", label: "Свыше 3 000 ₽" },
];

export const BRANDS: BrandProp[] = [
  { id: "ahmad", label: "Ahmad Tea" },
  { id: "jacobs", label: "Jacobs" },
  { id: "twinings", label: "Twinings" },
  { id: "greenfield", label: "Greenfield" },
];

// Рейтинги (только 5 и 4 звезды, как самые популярные)
export const RATINGS = [5, 4] as const;

// ---------- Вспомогательные компоненты ----------

function Stars({ value, max = 5 }: { value: number; max?: number }) {
  // Рисует value закрашенных звёзд (жёлтых) и max-value пустых (серых)
  return (
    <span style={{ display: "inline-flex", gap: 2 }}>
      {Array.from({ length: max }).map((_, i) => (
        <svg
          key={i}
          width="16"
          height="16"
          viewBox="0 0 16 16"
          fill={i < value ? "#F59E0B" : "#E5E7EB"}
          xmlns="http://www.w3.org/2000/svg"
        >
          <path d="M8 1l1.854 3.756L14 5.528l-3 2.924.708 4.126L8 10.5l-3.708 2.078L5 8.452 2 5.528l4.146-.772L8 1z" />
        </svg>
      ))}
    </span>
  );
}

// Кастомный чекбокс (скрываем нативный input, рисуем свой)
function Checkbox({
  checked,
  onChange,
  children,
}: {
  checked: boolean;
  onChange: () => void;
  children: React.ReactNode;
}) {
  return (
    <label className="filter-panel__checkbox-label">
      <span
        className={`filter-panel__checkbox-box ${checked ? "filter-panel__checkbox-box--checked" : ""}`}
        onClick={onChange}
      >
        {checked && (
          <svg width="10" height="8" viewBox="0 0 10 8" fill="none">
            <path
              d="M1 4l3 3 5-6"
              stroke="#fff"
              strokeWidth="1.8"
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </svg>
        )}
      </span>
      <span className="filter-panel__checkbox-text">{children}</span>
    </label>
  );
}

// Кастомная радио-кнопка (для выбора одного варианта из группы)
function Radio({
  checked,
  onChange,
  children,
}: {
  checked: boolean;
  onChange: () => void;
  children: React.ReactNode;
}) {
  return (
    <label className="filter-panel__checkbox-label">
      <span
        className={`filter-panel__radio-outer ${checked ? "filter-panel__radio-outer--checked" : ""}`}
        onClick={onChange}
      >
        {checked && <span className="filter-panel__radio-dot" />}
      </span>
      <span className="filter-panel__checkbox-text">{children}</span>
    </label>
  );
}

// ---------- Дерево категорий (рекурсивный компонент) ----------

function CategoryNode({
  node,
  selected,
  onToggle,
  depth,
}: {
  node: CategoryTreeNode;
  selected: string[];
  onToggle: (id: string) => void;
  depth: number;
}) {
  const [expanded, setExpanded] = useState(true);
  const hasChildren = node.children && node.children.length > 0;
  const checked = selected.includes(node.id);

  return (
    <div>
      <div
        className="filter-panel__tree-row"
        style={{ paddingLeft: depth * 20 }}
      >
        {hasChildren ? (
          <button
            className="filter-panel__tree-toggle"
            onClick={() => setExpanded((v) => !v)}
            type="button"
          >
            {expanded ? (
              <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                <path d="M2 3.5l3 3 3-3" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
              </svg>
            ) : (
              <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                <path d="M3.5 2l3 3-3 3" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
              </svg>
            )}
          </button>
        ) : (
          <span className="filter-panel__tree-toggle" />
        )}
        <Checkbox checked={checked} onChange={() => onToggle(node.id)}>
          {node.label}{" "}
          <span className="filter-panel__count">({node.count})</span>
        </Checkbox>
      </div>
      {hasChildren && expanded && (
        <div className="filter-panel__tree-children">
          {node.children!.map((child) => (
            <CategoryNode
              key={child.id}
              node={child}
              selected={selected}
              onToggle={onToggle}
              depth={depth + 1}
            />
          ))}
        </div>
      )}
    </div>
  );
}

function CategoryTree({
  nodes,
  selected,
  onToggle,
}: {
  nodes: CategoryTreeNode[];
  selected: string[];
  onToggle: (id: string) => void;
}) {
  return (
    <div className="filter-panel__tree">
      {nodes.map((node) => (
        <CategoryNode
          key={node.id}
          node={node}
          selected={selected}
          onToggle={onToggle}
          depth={0}
        />
      ))}
    </div>
  );
}

// ---------- Основной компонент ----------

export default function FilterPanel({ filters, onChange, onReset,categories,brands }: FilterPanelProps) {



  // Переключает элемент в массиве (категории или бренды):
  // если id уже есть — убрать, если нет — добавить.
  const toggleArr = (key: "categories" | "brands", id: string) =>
    onChange?.({ ...filters, [key]: filters[key].includes(id)
        ? filters[key].filter((x) => x !== id)
        : [...filters[key], id],
    });

  // Сброс всех фильтров в дефолтные значения
  const handleReset = () => {
    onChange?.({
      categories: [],
      priceFrom: "",
      priceTo: "",
      priceRange: null,
      brands: [],
      rating: null,
    });
    onReset?.();
  };

  const catItems = categories ?? CATEGORIES;
  const brandItems = brands ?? BRANDS;

  return (
    <div className="filter-panel">
      <h2 className="filter-panel__title">Фильтры</h2>

      {/* -------- Категории (дерево с expand/collapse) -------- */}
      <section className="filter-panel__section">
        <h3 className="filter-panel__section-title">Категории</h3>
        <CategoryTree
          nodes={catItems}
          selected={filters.categories}
          onToggle={(id) => toggleArr("categories", id)}
        />
      </section>

      {/* -------- Цена: кастомный ввод + готовые диапазоны -------- */}
      <section className="filter-panel__section">
        <h3 className="filter-panel__section-title">Цена</h3>
        <div className="filter-panel__price-inputs">
          <input
            className="filter-panel__price-input"
            placeholder="От"
            value={filters.priceFrom}
            type="number"
            min={0}
            onChange={(e) =>
              onChange?.({ ...filters, priceFrom: e.target.value, priceRange: null })
            }
          />
          <input
            className="filter-panel__price-input"
            placeholder="До"
            value={filters.priceTo}
            type="number"
            min={0}
            onChange={(e) =>
              onChange?.({ ...filters, priceTo: e.target.value, priceRange: null })
            }
          />
        </div>
        <div className="filter-panel__list">
          {PRICE_RANGES.map((pr) => (
            <Radio
              key={pr.id}
              checked={filters.priceRange === pr.id}
              onChange={() =>
                onChange?.({
                  ...filters,
                  priceRange: filters.priceRange === pr.id ? null : pr.id,
                  priceFrom: "",
                  priceTo: "",
                })
              }
            >
              {pr.label}
            </Radio>
          ))}
        </div>
      </section>

      {/* -------- Бренд (множественный выбор) -------- */}
      <section className="filter-panel__section">
        <h3 className="filter-panel__section-title">Бренд</h3>
        <div className="filter-panel__list">
          {BRANDS.map((b) => (
            <Checkbox
              key={b.id}
              checked={filters.brands.includes(b.id)}
              onChange={() => toggleArr("brands", b.id)}
            >
              {b.label}
            </Checkbox>
          ))}
        </div>
      </section>

      {/* -------- Рейтинг (множественный выбор, но выбирается только одно значение) -------- */}
      <section className="filter-panel__section">
        <h3 className="filter-panel__section-title">Рейтинг</h3>
        <div className="filter-panel__list">
          {RATINGS.map((r) => (
            <Checkbox
              key={r}
              checked={filters.rating === r}
              onChange={() =>
                onChange?.({ ...filters, rating: filters.rating === r ? null : r })
              }
            >
              <Stars value={r} />{" "}
              <span className="filter-panel__rating-label">и выше</span>
            </Checkbox>
          ))}
        </div>
      </section>

      <button
        className="filter-panel__btn filter-panel__btn--primary"
        onClick={() => onChange?.(filters)}
      >
        Применить фильтры
      </button>
      <button
        className="filter-panel__btn filter-panel__btn--reset"
        onClick={handleReset}
      >
        Сбросить все
      </button>
    </div>
  );
}
