import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), delete: vi.fn() }));
vi.mock("./api", () => ({ api: mocks }));
import { wishlistApi } from "./wishlistAPI";

beforeEach(() => Object.values(mocks).forEach((mock) => mock.mockReset()));

describe("wishlist api", () => {
  it("получает список из Laravel envelope", async () => {
    mocks.get.mockResolvedValue({ data: { data: [{ id: 1, product: { id: 7, name: "Ассам" } }], count: 1 } });
    await expect(wishlistApi.list()).resolves.toHaveLength(1);
    expect(mocks.get).toHaveBeenCalledWith("/api/wishlist");
  });

  it("добавляет product_id и удаляет по product id", async () => {
    mocks.post.mockResolvedValue({ data: { data: { id: 3, product: { id: 7, name: "Ассам" } } } });
    mocks.delete.mockResolvedValue({ data: { message: "ok" } });
    await wishlistApi.add(7);
    await wishlistApi.remove(7);
    expect(mocks.post).toHaveBeenCalledWith("/api/wishlist", { product_id: 7 });
    expect(mocks.delete).toHaveBeenCalledWith("/api/wishlist/7");
  });

  it("понимает check без дополнительной обёртки", async () => {
    mocks.get.mockResolvedValue({ data: { in_wishlist: true } });
    await expect(wishlistApi.check(7)).resolves.toBe(true);
  });
});
