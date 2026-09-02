// Фильтрация каталога по маршруту /catalog?category=...
// Категория, выбранная на лендинге категорий, должна реально управлять
// выборкой. Проверяем весь category_path (backend может вернуть категорию на
// уровне category, subcategory или sub_subcategory) и учитываем старые имена.

const CATEGORY_ALIASES: Record<string, string[]> = {
  "сладости": ["Сладости"],
};

function normalizeLabel(value: string): string {
  return value.trim().toLocaleLowerCase("ru-RU").replaceAll("ё", "е");
}

function categoryLabels(product: Record<string, any>): string[] {
  const labels: string[] = [];
  const path = product?.category_path;
  if (!path || typeof path !== "object") return labels;

  const visit = (value: unknown, depth = 0) => {
    if (value == null || depth > 3) return;
    if (typeof value === "string") {
      if (value.trim()) labels.push(value.trim());
      return;
    }
    if (Array.isArray(value)) {
      value.forEach((entry) => visit(entry, depth + 1));
      return;
    }
    if (typeof value !== "object") return;

    const record = value as Record<string, unknown>;
    if (typeof record.name === "string" && record.name.trim()) labels.push(record.name.trim());
    Object.entries(record).forEach(([key, nested]) => {
      if (key === "name" || key === "id" || key.endsWith("_id")) return;
      visit(nested, depth + 1);
    });
  };

  Object.values(path as Record<string, unknown>).forEach((value) => visit(value));
  return [...new Set(labels)];
}

export function getCatalogRouteCategory(search: string): string | null {
  return new URLSearchParams(search).get("category")?.trim() || null;
}

export function getCatalogQuery(search: string): string {
  return new URLSearchParams(search).get("q")?.trim() ?? "";
}

function productSearchHaystack(product: Record<string, any>): string {
  const values: string[] = [];
  const push = (value: unknown) => {
    if (typeof value === "string" && value.trim()) values.push(value);
  };

  push(product?.name);
  if (typeof product?.brand === "string") push(product.brand);
  else push(product?.brand?.name);
  push(product?.description);
  push(product?.ingredients);
  categoryLabels(product).forEach(push);
  return normalizeLabel(values.join(" "));
}

/**
 * Поисковый фильтр по q из URL: каждый токен запроса (разделённый пробелами,
 * без учёта регистра и «ё/е») должен встречаться в названии, бренде, описании,
 * составе или категориях товара.
 */
export function filterProductsByQuery<T extends Record<string, any>>(products: T[], query: string): T[] {
  const tokens = normalizeLabel(query).split(/\s+/).filter(Boolean);
  if (tokens.length === 0) return products;
  return products.filter((product) => {
    const haystack = productSearchHaystack(product);
    return tokens.every((token) => haystack.includes(token));
  });
}

/** Нормализованные имена-кандидаты для категории из URL: алиасы + оригинал. */
export function catalogRouteCandidates(raw: string): string[] {
  const key = normalizeLabel(raw || "");
  return (CATEGORY_ALIASES[key] ?? [raw]).map(normalizeLabel);
}

export function normalizeRouteLabel(value: string): string {
  return normalizeLabel(value);
}

/**
 * Категория из /category является полноценным фильтром каталога. Проверяем
 * весь category_path, потому что backend может вернуть название на уровне
 * category, subcategory или sub_subcategory.
 */
export function filterCatalogProducts<T extends Record<string, any>>(products: T[], search: string, minRating = 0): T[] {
  const queryTokens = normalizeLabel(getCatalogQuery(search)).split(/\s+/).filter(Boolean);
  const requested = getCatalogRouteCategory(search) ?? "";
  const requestedKey = normalizeLabel(requested);
  const candidates = requested
    ? (CATEGORY_ALIASES[requestedKey] ?? [requested]).map(normalizeLabel)
    : [];

  return products.filter((product) => {
    if (queryTokens.length > 0) {
      const haystack = productSearchHaystack(product);
      if (!queryTokens.every((token) => haystack.includes(token))) return false;
    }

    if ((product.rating_average ?? 0) < minRating) {
      return false;
    }

    if (candidates.length === 0) return true;
    return categoryLabels(product).some((label) => {
      const key = normalizeLabel(label);
      return candidates.includes(key)
        || (requestedKey.length >= 3 && (key.includes(requestedKey) || requestedKey.includes(key)));
    });
  });
}