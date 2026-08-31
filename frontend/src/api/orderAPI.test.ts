import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({ get: vi.fn(), put: vi.fn() }));
vi.mock("./api", () => ({ api: mocks }));
import { orderApi } from "./orderAPI";

// Изолируем преобразование ответов API от React-компонентов.
beforeEach(() => Object.values(mocks).forEach((mock) => mock.mockReset()));

describe("customer order api", () => {
  it("распаковывает коллекцию заказов", async () => {
    mocks.get.mockResolvedValue({ data: { data: [{ id: 1 }, { id: 2 }] } });
    await expect(orderApi.getOrders()).resolves.toHaveLength(2);
  });

  it("распаковывает карточку заказа", async () => {
    mocks.get.mockResolvedValue({ data: { data: { id: 12 } } });
    await expect(orderApi.getOrder(12)).resolves.toMatchObject({ id: 12 });
    expect(mocks.get).toHaveBeenCalledWith("/api/orders/12");
  });

  it("PUT отмены возвращает обновлённый Order", async () => {
    mocks.put.mockResolvedValue({ data: { data: { id: 12, status: "cancelled" } } });
    await expect(orderApi.cancelOrder(12)).resolves.toMatchObject({ status: "cancelled" });
  });
});
