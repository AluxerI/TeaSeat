import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));
vi.mock("./api", () => ({ api: mocks }));

import { giftConstructorApi } from "./giftConstructorAPI";

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
    expect(mocks.post).toHaveBeenCalledWith("/api/gift-constructor/simple/gifts", request);
  });
});
