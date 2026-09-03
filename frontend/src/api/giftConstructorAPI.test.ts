import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));
vi.mock("./api", () => ({ api: mocks }));

import { giftConstructorApi } from "./giftConstructorAPI";
import { testBox, testQuote, testSizes } from "../components/constructor/testFixtures";

beforeEach(() => Object.values(mocks).forEach((mock) => mock.mockReset()));

describe("gift constructor api", () => {
  it("загружает серверные опции упрощённого конструктора", async () => {
    mocks.get.mockResolvedValue({ data: { data: { boxes: [{ id: 4 }] } } });
    await expect(giftConstructorApi.getSimpleOptions()).resolves.toMatchObject({
      boxes: [{ id: 4 }],
    });
    expect(mocks.get).toHaveBeenCalledWith("/api/gift-constructor/simple/options");
  });

  it("отправляет product_size ids на серверный расчёт", async () => {
    mocks.post.mockResolvedValue({ data: { data: { valid: true, totals: { final_total: 950 } } } });
    const request = {
      box_profile_id: 4,
      tea_product_size_ids: [11, 12],
      sweet_product_size_ids: [21],
      quantity: 1,
    };
    await giftConstructorApi.quoteSimple(request);
    expect(mocks.post).toHaveBeenCalledWith("/api/gift-constructor/simple/quote", request);
  });

  it("создаёт Gift, а не отдельные cart items", async () => {
    mocks.post.mockResolvedValue({ data: { data: { id: 40, version: 1 } } });
    const request = {
      box_profile_id: 4,
      name: "Чайный подарок",
      tea_product_size_ids: [11, 12],
      sweet_product_size_ids: [21],
    };
    await giftConstructorApi.createSimpleGift(request);
    expect(mocks.post).toHaveBeenCalledWith("/api/gift-constructor/simple/gifts", request, { timeout: 15000 });
  });

  it.each(["simple", "advanced"] as const)("проверяет options %s и передаёт отмену запроса", async (mode) => {
    mocks.get.mockResolvedValue({ data: { data: { boxes: [testBox], ...(mode === "simple"
      ? { tea_product_sizes: [testSizes[0]], sweet_product_sizes: [testSizes[1]] }
      : { product_sizes: testSizes }) } } });
    const signal = new AbortController().signal;
    await expect(giftConstructorApi.loadOptions(mode, signal)).resolves.toEqual({ boxes: [testBox], product_sizes: testSizes, cell_size_mm: null });
    expect(mocks.get).toHaveBeenCalledWith(`/api/gift-constructor/${mode}/options`, { signal, timeout: 15000 });
  });

  it("отличает пустой каталог от отсутствующего поля в ответе", async () => {
    mocks.get.mockResolvedValueOnce({ data: { data: { boxes: [testBox], product_sizes: [] } } });
    await expect(giftConstructorApi.loadOptions("advanced", new AbortController().signal)).resolves.toEqual({ boxes: [testBox], product_sizes: [], cell_size_mm: null });
    mocks.get.mockResolvedValueOnce({ data: { data: { boxes: [testBox] } } });
    await expect(giftConstructorApi.loadOptions("advanced", new AbortController().signal)).rejects.toThrow(/общий каталог/);
  });

  it("берёт физический размер ячейки из API, не подставляя собственный", async () => {
    mocks.get.mockResolvedValue({ data: { boxes: [testBox], product_sizes: testSizes, cell_size_mm: 12 } });
    await expect(giftConstructorApi.loadOptions("advanced", new AbortController().signal)).resolves.toMatchObject({ cell_size_mm: 12 });
  });

  it("не принимает HTML frontend вместо ответа backend", async () => {
    mocks.get.mockResolvedValue({ data: "<!doctype html><html>Frontend fallback</html>" });
    await expect(giftConstructorApi.loadOptions("simple", new AbortController().signal)).rejects.toThrow(/proxy/);
  });

  it("берёт форматы именно выбранной коробки", async () => {
    const signal = new AbortController().signal;
    mocks.get.mockResolvedValue({ data: { data: { box: testBox, product_sizes: testSizes } } });
    await expect(giftConstructorApi.getBoxProducts(4, signal)).resolves.toMatchObject({ product_sizes: testSizes });
    expect(mocks.get).toHaveBeenCalledWith("/api/gift-constructor/boxes/4/products", { signal, timeout: 15000 });
    await expect(giftConstructorApi.getBoxProducts(5, signal)).rejects.toThrow(/Некорректный ответ/);
  });

  it("отклоняет неподдерживаемую единицу измерения", async () => {
    mocks.get.mockResolvedValue({ data: { box: testBox, product_sizes: [{ ...testSizes[0], product: { ...testSizes[0].product, stock_unit: "ounce" } }] } });
    await expect(giftConstructorApi.getBoxProducts(4, new AbortController().signal)).rejects.toThrow(/Некорректный ответ/);
  });

  it("отклоняет некорректный онлайн-остаток", async () => {
    mocks.get.mockResolvedValue({ data: { box: testBox, product_sizes: [{
      ...testSizes[0],
      product: { ...testSizes[0].product, total_quantity: -1 },
    }] } });
    await expect(giftConstructorApi.getBoxProducts(4, new AbortController().signal)).rejects.toThrow(/Некорректный ответ/);
  });

  it("использует advanced validate, quote и gifts без подмены item ids", async () => {
    const request = { box_profile_id: 4, items: [{ client_item_id: "11111111-1111-4111-8111-111111111111", product_size_id: 11, position_x: 2, position_y: 1, is_rotated: true }] };
    const signal = new AbortController().signal;
    mocks.post.mockResolvedValueOnce({ data: { valid: true } }).mockResolvedValueOnce({ data: { data: testQuote } }).mockResolvedValueOnce({ data: { data: { id: 40, version: 1 } } });
    await giftConstructorApi.validateAdvanced(request, signal);
    await expect(giftConstructorApi.quoteAdvanced(request, signal)).resolves.toEqual(testQuote);
    await giftConstructorApi.createAdvancedGift({ ...request, name: "Подарок" });
    expect(mocks.post).toHaveBeenNthCalledWith(1, "/api/gift-constructor/advanced/validate-layout", request, { signal, timeout: 15000 });
    expect(mocks.post).toHaveBeenNthCalledWith(2, "/api/gift-constructor/advanced/quote", { ...request, quantity: 1 }, { signal, timeout: 15000 });
    expect(mocks.post).toHaveBeenNthCalledWith(3, "/api/gift-constructor/advanced/gifts", { ...request, name: "Подарок" }, { timeout: 15000 });
  });

  it("не считает раскладку подтверждённой без valid:true", async () => {
    mocks.post.mockResolvedValue({ data: { valid: false } });
    await expect(giftConstructorApi.validateAdvanced({ box_profile_id: 4, items: [] })).rejects.toThrow(/раскладка не подтверждена/);
  });
});
