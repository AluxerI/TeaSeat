import { useState, useMemo } from "react";
import CatalogHeader from "../components/catalog-part/CatalogHeaderSort/CatalogHeaderProps";
import FilterPanel, {PRICE_RANGES, FilterState, CategoryTreeNode, BrandProp, PriceRangeProp} from "../components/catalog-part/FilterList";
import { ProductList } from "../components/productList";
import Header from "../ui/header/header";
import Footer from "../ui/footer/Footer";
import { catalogApi } from "../api/catalogAPI";
import { useAsync } from "../hooks/useAsync";
import { Category, Product } from "../interfaces/catalog";
import { sort_by_brands } from "../utils/product_methods";

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

// Строит из API-категорий дерево CategoryTreeNode с подсчётом продуктов
function buildCategoryTree(categories: { id: number; name: string; subcategories: { id: number; name: string; sub_subcategories: { id: number; name: string }[] }[] }[], products: { category_path: { category: string; subcategory: string; sub_subcategory: string } }[]): CategoryTreeNode[] {
  return categories
    .map(cat => {
      const catProducts = products.filter(p => p.category_path.category === cat.name);
      const subTree = cat.subcategories
        .map(sub => {
          const subProducts = catProducts.filter(p => p.category_path.subcategory === sub.name);
          const subsubTree = sub.sub_subcategories
            .map(ss => ({
              id: `subsub_${ss.id}`,
              label: ss.name,
              count: subProducts.filter(p => p.category_path.sub_subcategory === ss.name).length,
            }))
            .filter(n => n.count > 0);
          return {
            id: `sub_${sub.id}`,
            label: sub.name,
            count: subProducts.length,
            children: subsubTree.length > 0 ? subsubTree : undefined,
          };
        })
        .filter(n => n.count > 0);
      return {
        id: `cat_${cat.id}`,
        label: cat.name,
        count: catProducts.length,
        children: subTree.length > 0 ? subTree : undefined,
      };
    })
    .filter(n => n.count > 0);
}

// Превращает дерево категорий в плоский lookup для фильтрации
function flattenCategoryTree(nodes: CategoryTreeNode[]): Record<string, { level: "category" | "subcategory" | "sub_subcategory"; label: string }> {
  const lookup: Record<string, { level: "category" | "subcategory" | "sub_subcategory"; label: string }> = {};
  function walk(list: CategoryTreeNode[]) {
    for (const n of list) {
      const level = n.id.startsWith("cat_") ? "category" : n.id.startsWith("sub_") ? "subcategory" : "sub_subcategory";
      lookup[n.id] = { level, label: n.label };
      if (n.children) walk(n.children);
    }
  }
  walk(nodes);
  return lookup;
}

// Превращает текущие фильтры в массив тегов для хедера.
function filtersToTags(filters: FilterState, categoryLookup: Record<string, { level: string; label: string }>, brand: Record<string, string>, price: PriceRangeProp[]): { id: string; label: string }[] {
  const tags: { id: string; label: string }[] = [];

  for (const id of filters.categories) {
    const entry = categoryLookup[id];
    tags.push({ id, label: entry?.label ?? id });
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
function filterProducts(products: Product[], filters: FilterState, categoryLookup: Record<string, { level: "category" | "subcategory" | "sub_subcategory"; label: string }>, brands: BrandProp[]): Product[] {
  // Маппим id выбранных брендов в их label ("Ahmad Tea"...)
  const brandLabels = filters.brands.map(
    (id) => brands.find((b) => b.id === id)?.label
  ).filter(Boolean);

  return products.filter((p) => {
    // ----- Категории (иерархические: category / subcategory / sub_subcategory) -----
    if (filters.categories.length > 0) {
      const catMatch = filters.categories.some((id) => {
        const entry = categoryLookup[id];
        if (!entry) return false;
        return p.category_path[entry.level] === entry.label;
      });
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

  // Строим дерево категорий из API-данных
  const categoryTree: CategoryTreeNode[] = useMemo(() => {
    if (!categories.data || !products.data) return [];
    return buildCategoryTree(categories.data, products.data);
  }, [categories.data, products.data]);

  // Плоский lookup id → { level, label } для быстрой фильтрации
  const categoryLookup = useMemo(() => flattenCategoryTree(categoryTree), [categoryTree]);

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

  const brandLabelsRecord: Record<string, string> = useMemo(() => {
    const record: Record<string, string> = {};
    for (const b of brand_prop) record[b.id] = b.label;
    return record;
  }, [brand_prop]);

  const filtered = useMemo(() => {
    const f = filterProducts(products.data ?? [], filters, categoryLookup, brand_prop);
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
  }, [products.data, filters, categoryLookup, brand_prop, sortValue]);

  // Обработчики для FilterPanel
  const handleChange = (next: FilterState) => setFilters(next);
  const handleReset = () => setFilters(DEFAULT_FILTERS);

  // Теги активных фильтров (показываются в CatalogHeader как чипы)
  const filterTags = useMemo(() => filtersToTags(filters, categoryLookup, brandLabelsRecord, PRICE_RANGES), [filters, categoryLookup, brandLabelsRecord]);

  // Когда пользователь тыкает крестик на чипе → убираем соответствующий фильтр
  const handleRemoveTag = (tagId: string) => {
    if (tagId.startsWith("cat_") || tagId.startsWith("sub_") || tagId.startsWith("subsub_")) {
      setFilters((prev) => ({
        ...prev,
        categories: prev.categories.filter((c) => c !== tagId),
      }));
    } else if (tagId.startsWith("brand_")) {
      const brandId = tagId.slice(6);
      setFilters((prev) => ({
        ...prev,
        brands: prev.brands.filter((b) => b !== brandId),
      }));
    } else if (tagId.startsWith("price_")) {
      setFilters((prev) => ({ ...prev, priceRange: null, priceFrom: "", priceTo: "" }));
    } else if (tagId.startsWith("rating_")) {
      setFilters((prev) => ({ ...prev, rating: null }));
    }
  };

  const handleShowAll = () => setFilters(DEFAULT_FILTERS);

  return (
    <>
      <Header />
      <span className="filters-and-grid">
        <FilterPanel filters={filters} onChange={handleChange} onReset={handleReset} brands={brand_prop} categories={categoryTree}/>
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
