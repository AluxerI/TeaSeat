import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() }));
vi.mock("./api", () => ({ api: mocks }));
import { cartApi } from "./cartAPI";

// Проверяем договор API-слоя с backend: URL, payload, Laravel envelope и заголовки.
beforeEach(() => Object.values(mocks).forEach((mock) => mock.mockReset()));

describe("customer cart api", () => {
  it("распаковывает Laravel Resource", async () => {
    mocks.get.mockResolvedValue({ data: { data: { id: 7, items: [], gifts: [] } } });
    await expect(cartApi.getCart()).resolves.toMatchObject({ id: 7 });
  });

  it("quote всегда отправляет выбранные товары и подарки", async () => {
    mocks.post.mockResolvedValue({ data: { data: { final_total: 900 } } });
    await cartApi.quote({ cart_item_ids: [3], cart_gift_ids: [8] });
    expect(mocks.post).toHaveBeenCalledWith("/api/cart/quote", { cart_item_ids: [3], cart_gift_ids: [8] });
  });

  it("добавляет доставку в quote только после выбора метода", async () => {
    mocks.post.mockResolvedValue({ data: { data: { final_total: 1100 } } });
    await cartApi.quote({ cart_item_ids: [3], cart_gift_ids: [] }, { addressId: 4, methodId: 2 });
    expect(mocks.post).toHaveBeenCalledWith("/api/cart/quote", {
      cart_item_ids: [3], cart_gift_ids: [], shipping_address_id: 4, delivery_method_id: 2,
    });
  });

  it("передаёт Idempotency-Key при checkout", async () => {
    mocks.post.mockResolvedValue({ data: { data: { id: 91 } } });
    await cartApi.checkout({
      cart_item_ids: [3], cart_gift_ids: [], shipping_address_id: 4,
      delivery_method_id: 2, payment_method: "card",
    }, "checkout-test-0001");
    expect(mocks.post).toHaveBeenCalledWith("/api/checkout", expect.objectContaining({ cart_item_ids: [3], shipping_address_id: 4 }), { headers: { "Idempotency-Key": "checkout-test-0001" } });
  });
});
