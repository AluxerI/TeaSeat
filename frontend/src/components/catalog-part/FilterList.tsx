import { useState } from "react";

interface Category {
  id: string;
  label: string;
  count: number;
}

interface Brand {
  id: string;
  label: string;
}

interface PriceRange {
  id: string;
  label: string;
}

interface FilterState {
  categories: string[];
  priceFrom: string;
  priceTo: string;
  priceRange: string | null;
  brands: string[];
  rating: number | null;
}

interface FilterPanelProps {
  onApply?: (filters: FilterState) => void;
  onReset?: () => void;
}

const CATEGORIES: Category[] = [
  { id: "tea", label: "Чай", count: 156 },
  { id: "coffee", label: "Кофе", count: 89 },
  { id: "desserts", label: "Десерты", count: 67 },
  { id: "gift_sets", label: "Подарочные наборы", count: 23 },
  { id: "accessories", label: "Аксессуары", count: 45 },
];

const PRICE_RANGES: PriceRange[] = [
  { id: "0-500", label: "До 500 ₽" },
  { id: "500-1500", label: "500 – 1 500 ₽" },
  { id: "1500-3000", label: "1 500 – 3 000 ₽" },
  { id: "3000+", label: "Свыше 3 000 ₽" },
];

const BRANDS: Brand[] = [
  { id: "ahmad", label: "Ahmad Tea" },
  { id: "jacobs", label: "Jacobs" },
  { id: "twinings", label: "Twinings" },
  { id: "greenfield", label: "Greenfield" },
];

const RATINGS = [5, 4] as const;

function Stars({ value, max = 5 }: { value: number; max?: number }) {
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

export default function FilterPanel({ onApply, onReset }: FilterPanelProps) {
  const [filters, setFilters] = useState<FilterState>({
    categories: ["tea"],
    priceFrom: "",
    priceTo: "",
    priceRange: null,
    brands: [],
    rating: null,
  });

  const toggleArr = (key: "categories" | "brands", id: string) =>
    setFilters((prev) => ({
      ...prev,
      [key]: prev[key].includes(id)
        ? prev[key].filter((x) => x !== id)
        : [...prev[key], id],
    }));

  const handleReset = () => {
    setFilters({
      categories: [],
      priceFrom: "",
      priceTo: "",
      priceRange: null,
      brands: [],
      rating: null,
    });
    onReset?.();
  };

  return (
    <div className="filter-panel">
      <h2 className="filter-panel__title">Фильтры</h2>

      <section className="filter-panel__section">
        <h3 className="filter-panel__section-title">Категории</h3>
        <div className="filter-panel__list">
          {CATEGORIES.map((cat) => (
            <Checkbox
              key={cat.id}
              checked={filters.categories.includes(cat.id)}
              onChange={() => toggleArr("categories", cat.id)}
            >
              {cat.label}{" "}
              <span className="filter-panel__count">({cat.count})</span>
            </Checkbox>
          ))}
        </div>
      </section>

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
              setFilters((p) => ({
                ...p,
                priceFrom: e.target.value,
                priceRange: null,
              }))
            }
          />
          <input
            className="filter-panel__price-input"
            placeholder="До"
            value={filters.priceTo}
            type="number"
            min={0}
            onChange={(e) =>
              setFilters((p) => ({
                ...p,
                priceTo: e.target.value,
                priceRange: null,
              }))
            }
          />
        </div>
        <div className="filter-panel__list">
          {PRICE_RANGES.map((pr) => (
            <Radio
              key={pr.id}
              checked={filters.priceRange === pr.id}
              onChange={() =>
                setFilters((p) => ({
                  ...p,
                  priceRange: p.priceRange === pr.id ? null : pr.id,
                  priceFrom: "",
                  priceTo: "",
                }))
              }
            >
              {pr.label}
            </Radio>
          ))}
        </div>
      </section>

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

      <section className="filter-panel__section">
        <h3 className="filter-panel__section-title">Рейтинг</h3>
        <div className="filter-panel__list">
          {RATINGS.map((r) => (
            <Checkbox
              key={r}
              checked={filters.rating === r}
              onChange={() =>
                setFilters((p) => ({ ...p, rating: p.rating === r ? null : r }))
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
        onClick={() => onApply?.(filters)}
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
