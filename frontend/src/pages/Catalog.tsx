import { useState, useMemo } from "react";
import CatalogHeader from "../components/catalog-part/CatalogHeaderSort/CatalogHeaderProps";
import FilterPanel, {CATEGORIES, PRICE_RANGES, FilterState, CategoryProp, BrandProp, PriceRangeProp} from "../components/catalog-part/FilterList";
import { ProductList } from "../components/productList";
import Header from "../ui/header/header";
import Footer from "../ui/footer/footer";
import { catalogApi } from "../api/catalogAPI";
import { useAsync } from "../hooks/useAsync";
import { Category, Product } from "../interfaces/catalog";
import { sort_by_category, sort_by_brands } from "../utils/product_methods";

// Дефолтное состояние — все фильтры пустые (показаны все товары)
const DEFAULT_FILTERS: FilterState = {
  categories: [],
  priceFrom: "",
  priceTo: "",
  priceRange: null,
  brands: [],
  rating: null,
};

// ---------- Утилиты ----------

// Превращает текущие фильтры в массив тегов для хедера.
// Каждый тег — это чип с id ("cat_tea", "brand_ahmad") и человекопонятной подписью.
function filtersToTags(filters: FilterState,categories:CategoryProp[],brand: Record<string, string>,price:PriceRangeProp[]): { id: string; label: string }[] {
  const tags: { id: string; label: string }[] = [];

  for (const id of filters.categories) {
    const cat = categories.find((c) => c.id === id);
    tags.push({ id: `cat_${id}`, label: cat?.label ?? id });
  }

  for (const id of filters.brands) {
    tags.push({ id: `brand_${id}`, label: brand[id] ?? id });
  }

  if (filters.priceRange) {
    const pr = price.find((p) => p.id === filters.priceRange);
    if (pr) tags.push({ id: `price_${pr.id}`, label: pr.label });
  } else if (filters.priceFrom || filters.priceTo) {
    tags.push({
      id: "price_custom",
      label: `${filters.priceFrom || "0"} – ${filters.priceTo || "∞"} ₽`,
    });
  }

  if (filters.rating) {
    tags.push({ id: `rating_${filters.rating}`, label: `${filters.rating}★ и выше` });
  }

  return tags;
}

// Фильтрация массива продуктов по текущему состоянию фильтров.
// Фильтры хранят id ("tea", "ahmad"), а в продуктах лежат названия ("Чай", "Ahmad Tea").
// Поэтому сначала маппим id → label, а потом сравниваем label с полем продукта.
function filterProducts(products: Product[], filters: FilterState,categories: CategoryProp[],brands:BrandProp[]): Product[] {
  // Маппим id выбранных категорий в их label ("Чай", "Кофе"...)
  const catLabels = filters.categories.map(
    (id) => categories.find((c) => c.id === id)?.label
  ).filter(Boolean);
  // Маппим id выбранных брендов в их label ("Ahmad Tea"...)
  const brandLabels = filters.brands.map(
    (id) => brands.find((b) => b.id === id)?.label
  ).filter(Boolean);

  return products.filter((p) => {
    // ----- Категории -----
    if (catLabels.length > 0) {
      const catMatch = catLabels.some(
        (label) => p.category_path.category === label
      );
      if (!catMatch) return false;
    }

    // ----- Цена -----
    if (filters.priceRange) {
      // Выбран готовый диапазон
      const price = p.final_price;
      switch (filters.priceRange) {
        case "0-500": if (price >= 500) return false; break;
        case "500-1500": if (price < 500 || price >= 1500) return false; break;
        case "1500-3000": if (price < 1500 || price >= 3000) return false; break;
        case "3000+": if (price < 3000) return false; break;
      }
    } else {
      // Кастомный диапазон (От … До)
      if (filters.priceFrom && p.final_price < Number(filters.priceFrom)) return false;
      if (filters.priceTo && p.final_price > Number(filters.priceTo)) return false;
    }

    // ----- Бренд -----
    if (brandLabels.length > 0) {
      const brandMatch = brandLabels.some(
        (label) => p.brand === label
      );
      if (!brandMatch) return false;
    }

    // Все проверки пройдены — товар подходит
    return true;
  });
}

// ---------- Страница каталога ----------
// Это главный компонент-оркестратор: он загружает данные, управляет фильтрами
// и передаёт отфильтрованный список дочерним компонентам.

export const PageCatalog = () => {
  // Состояние фильтров — живёт здесь, а FilterPanel только отображает и сообщает об изменениях
  const [filters, setFilters] = useState<FilterState>(DEFAULT_FILTERS);
  const [sortValue, setSortValue] = useState("popular");

  // Загрузка данных с бэкенда (один раз при монтировании)
  const products = useAsync(() => catalogApi.getProducts(), true);
  const categories = useAsync(() => catalogApi.getCategory(), true);
  const meta = useAsync(() => catalogApi.getMeta(), true);

  // Вычисляем категории и бренды динамически из данных API
  const categories_prop: CategoryProp[] = useMemo(() => {
    if (!categories.data || !products.data) return [];
    const catMap = sort_by_category(categories.data, products.data, "object");
    return (catMap instanceof Set)
      ? categories.data.map(cat => ({
          id: cat.name.toLowerCase().replace(/\s+/g, '_'),
          label: cat.name,
          count: catMap.add(cat.name)?.size ?? 0,
        }))
      : [];
  }, [categories.data, products.data]);

  // Собираем уникальные бренды через Set (sort_by_brands), превращаем в BrandProp[]
  const brand_prop: BrandProp[] = useMemo(() => {
    const brandsSet = sort_by_brands(products.data ?? [], "object");
    if (brandsSet instanceof Set) {
      return Array.from(brandsSet).map(name => ({
        id: name.toLowerCase().replace(/\s+/g, '_'),
        label: name,
      }));
    }
    return [];
  }, [products.data]);

  console.info(brand_prop);
  console.info(categories_prop)

  const brandLabelsRecord: Record<string, string> = useMemo(() => {
    const record: Record<string, string> = {};
    for (const b of brand_prop) record[b.id] = b.label;
    return record;
  }, [brand_prop]);

  const filtered = useMemo(() => {
    const f = filterProducts(products.data ?? [], filters, categories_prop, brand_prop);
    switch (sortValue) {
      case "price_asc":
        return [...f].sort((a, b) => a.final_price - b.final_price);
      case "price_desc":
        return [...f].sort((a, b) => b.final_price - a.final_price);
      case "new":
        return [...f].sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
      case "popular":
      default:
        return [...f].sort((a, b) => b.sold_count - a.sold_count);
    }
  }, [products.data, filters, categories_prop, brand_prop, sortValue]);

  // Обработчики для FilterPanel
  const handleChange = (next: FilterState) => setFilters(next);
  const handleReset = () => setFilters(DEFAULT_FILTERS);

  // Теги активных фильтров (показываются в CatalogHeader как чипы)
  const filterTags = useMemo(() => filtersToTags(filters, categories_prop, brandLabelsRecord, PRICE_RANGES), [filters, categories_prop, brandLabelsRecord]);

  // Когда пользователь тыкает крестик на чипе → убираем соответствующий фильтр
  const handleRemoveTag = (tagId: string) => {
    const [prefix, ...rest] = tagId.split("_");
    const id = rest.join("_");

    if (prefix === "cat") {
      setFilters((prev) => ({
        ...prev,
        categories: prev.categories.filter((c) => c !== id),
      }));
    } else if (prefix === "brand") {
      setFilters((prev) => ({
        ...prev,
        brands: prev.brands.filter((b) => b !== id),
      }));
    } else if (prefix === "price") {
      setFilters((prev) => ({ ...prev, priceRange: null, priceFrom: "", priceTo: "" }));
    } else if (prefix === "rating") {
      setFilters((prev) => ({ ...prev, rating: null }));
    }
  };

  const handleShowAll = () => setFilters(DEFAULT_FILTERS);

  return (
    <>
      <Header />
      <span className="filters-and-grid">
        <FilterPanel filters={filters} onChange={handleChange} onReset={handleReset} brands={brand_prop} categories={categories_prop}/>
        <div className="product-and-header">
          <CatalogHeader
            total={meta.data?.total_products ?? filtered.length}
            tags={filterTags}
            onRemoveTag={handleRemoveTag}
            onShowAll={handleShowAll}
            sortValue={sortValue}
            onSortChange={setSortValue}
          />
          <ProductList products={filtered} categories={categories.data ?? []}/>
        </div>
      </span>
      <Footer />
    </>
  );
};
