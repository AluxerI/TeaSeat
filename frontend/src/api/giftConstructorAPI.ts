import { api } from "./api";
import { unwrapData } from "./unwrap";
import type {
  Gift,
  SimpleConstructorOptions,
  SimpleGiftCreateRequest,
  SimpleGiftQuote,
  SimpleGiftQuoteRequest,
  AdvancedConstructorOptions,
  AdvancedGiftSelection,
  BoxProducts,
  ConstructorProductSize,
  GiftSizeProfile,
} from "../interfaces/giftConstructor";

const SIMPLE_PATH = "/api/gift-constructor/simple";
const ADVANCED_PATH = "/api/gift-constructor/advanced";
const readConfig = (signal?: AbortSignal) => ({ signal, timeout: 15000 });

function validProfile(box: GiftSizeProfile): boolean {
  return Boolean(box && Number.isSafeInteger(box.id) && box.id > 0 && typeof box.name === "string"
    && Number.isSafeInteger(box.width_cells) && box.width_cells > 0
    && Number.isSafeInteger(box.height_cells) && box.height_cells > 0);
}

function validSize(size: ConstructorProductSize): boolean {
  const packaging = size?.packaging_template;
  const validPackaging = packaging === null || packaging === undefined || Boolean(
    Number.isInteger(packaging.id) && packaging.id > 0
    && typeof packaging.code === "string" && typeof packaging.name === "string"
    && typeof packaging.kind === "string" && typeof packaging.image_url === "string",
  );
  return Boolean(size && Number.isInteger(size.id) && validProfile(size.size) && validPackaging
    && ["tea", "sweet", "general"].includes(size.constructor_role) && typeof size.label === "string"
    && size.product && typeof size.product.name === "string"
    && ["gram", "piece"].includes(size.product.stock_unit)
    && Number.isFinite(size.product.price) && size.product.price_unit_quantity > 0
    && Number.isSafeInteger(size.product.total_quantity)
    && size.product.total_quantity >= 0
    && Number.isInteger(size.product_quantity) && size.product_quantity > 0);
}

export const giftConstructorApi = {
  async loadOptions(mode: "simple" | "advanced", signal: AbortSignal): Promise<{ boxes: GiftSizeProfile[]; product_sizes: ConstructorProductSize[]; cell_size_mm: number | null }> {
    const response = await api.get<{ data: SimpleConstructorOptions | AdvancedConstructorOptions }>(
      `/api/gift-constructor/${mode}/options`, readConfig(signal),
    );
    const data = unwrapData(response.data);
    if (!data || !Array.isArray(data.boxes) || !data.boxes.every(validProfile)) {
      throw new Error("Некорректный ответ API: ожидается список коробок. Проверьте backend и proxy /api.");
    }
    // Сохраняем общий каталог для диагностики. Он НЕ заменяет отфильтрованный
    // ответ boxes/{id}/products: там backend дополнительно проверяет совместимость.
    let sizes: ConstructorProductSize[];
    if (mode === "simple") {
      const simple = data as SimpleConstructorOptions;
      if (!Array.isArray(simple.tea_product_sizes) || !Array.isArray(simple.sweet_product_sizes)) {
        throw new Error("Некорректный ответ API: отсутствуют списки форматов простого конструктора.");
      }
      sizes = [...simple.tea_product_sizes, ...simple.sweet_product_sizes];
    } else {
      sizes = (data as AdvancedConstructorOptions).product_sizes;
    }
    if (!Array.isArray(sizes) || !sizes.every(validSize)) {
      throw new Error("Некорректный ответ API: ожидается общий каталог форматов конструктора.");
    }
    return { boxes: data.boxes, product_sizes: sizes, cell_size_mm: Number.isFinite(data.cell_size_mm) && data.cell_size_mm > 0 ? data.cell_size_mm : null };
  },

  async getBoxProducts(boxId: number, signal: AbortSignal): Promise<BoxProducts> {
    const response = await api.get<{ data: BoxProducts }>(`/api/gift-constructor/boxes/${boxId}/products`, readConfig(signal));
    const data = unwrapData(response.data);
    if (!data || !validProfile(data.box) || data.box.id !== boxId
      || !Array.isArray(data.product_sizes) || !data.product_sizes.every(validSize)) {
      throw new Error("Некорректный ответ API: ожидаются форматы товаров выбранной коробки.");
    }
    return data;
  },

  async validateAdvanced(request: AdvancedGiftSelection, signal?: AbortSignal): Promise<void> {
    const response = await api.post<{ data: { valid: boolean } }>(`${ADVANCED_PATH}/validate-layout`, request, readConfig(signal));
    if (unwrapData(response.data)?.valid !== true) throw new Error("Некорректный ответ API: раскладка не подтверждена.");
  },

  async quoteAdvanced(request: AdvancedGiftSelection, signal?: AbortSignal): Promise<SimpleGiftQuote> {
    const response = await api.post<SimpleGiftQuote | { data: SimpleGiftQuote }>(`${ADVANCED_PATH}/quote`, { ...request, quantity: 1 }, readConfig(signal));
    return unwrapData(response.data);
  },

  async createAdvancedGift(request: AdvancedGiftSelection & { name: string }): Promise<Gift> {
    const response = await api.post<Gift | { data: Gift }>(`${ADVANCED_PATH}/gifts`, request, { timeout: 15000 });
    return unwrapData(response.data);
  },
  async getSimpleOptions(): Promise<SimpleConstructorOptions> {
    const response = await api.get<
      SimpleConstructorOptions | { data: SimpleConstructorOptions }
    >(`${SIMPLE_PATH}/options`);
    return unwrapData(response.data);
  },

  async quoteSimple(request: SimpleGiftQuoteRequest, signal?: AbortSignal): Promise<SimpleGiftQuote> {
    const response = await api.post<SimpleGiftQuote | { data: SimpleGiftQuote }>(
      `${SIMPLE_PATH}/quote`,
      request,
      ...(signal ? [readConfig(signal)] : []),
    );
    return unwrapData(response.data);
  },

  async createSimpleGift(request: SimpleGiftCreateRequest): Promise<Gift> {
    const response = await api.post<Gift | { data: Gift }>(
      `${SIMPLE_PATH}/gifts`,
      request,
      { timeout: 15000 },
    );
    return unwrapData(response.data);
  },
};
