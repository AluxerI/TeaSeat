import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), patch: vi.fn(), put: vi.fn() }));
vi.mock("../api/api", () => ({ api: mocks }));

import {
  cancelManagerOrder,
  changeManagerOrderItem,
  fetchFulfillmentIssues,
  fetchManagerOrders,
  hideManagerReview,
  transitionManagerRequest,
} from "./api";

beforeEach(() => Object.values(mocks).forEach((mock) => mock.mockReset()));

describe("manager api", () => {
  it("передаёт фильтры заказа и не отправляет undefined", async () => {
    mocks.get.mockResolvedValue({ data: { data: [], meta: {} } });
    await fetchManagerOrders({ sales_channel: "seller", has_issue: true, page: 2 });
    expect(mocks.get).toHaveBeenCalledWith("/api/manager/orders", {
      params: { sales_channel: "seller", has_issue: true, page: 2 },
    });
  });

  it("передаёт scoped-фильтры складских проблем", async () => {
    mocks.get.mockResolvedValue({ data: { data: [], summary: {}, meta: {} } });
    await fetchFulfillmentIssues({ warehouse_id: 7, mine: true, status: "in_review" });
    expect(mocks.get).toHaveBeenCalledWith("/api/manager/fulfillment-issues", {
      params: { warehouse_id: 7, mine: true, status: "in_review" },
    });
  });

  it("обрезает причину отмены", async () => {
    mocks.post.mockResolvedValue({ data: { message: "ok", data: {} } });
    await cancelManagerOrder(12, "  клиент отказался  ");
    expect(mocks.post).toHaveBeenCalledWith("/api/manager/orders/12/cancel", { reason: "клиент отказался" });
  });

  it("не теряет operation_id item-команды", async () => {
    mocks.patch.mockResolvedValue({ data: { message: "ok", data: {}, already_applied: false } });
    await changeManagerOrderItem(12, 4, { operation_id: "3d7d00dc-d6e4-4d84-b69d-5b2718d3ce20", reason: "Согласовано", quantity: 2 });
    expect(mocks.patch).toHaveBeenCalledWith("/api/manager/orders/12/items/4", {
      operation_id: "3d7d00dc-d6e4-4d84-b69d-5b2718d3ce20",
      reason: "Согласовано",
      quantity: 2,
    });
  });

  it("отправляет manager_comment только завершающей команде", async () => {
    mocks.post.mockResolvedValue({ data: { message: "ok", data: {} } });
    await transitionManagerRequest(5, "take");
    await transitionManagerRequest(5, "resolve", "Выполнено");
    expect(mocks.post).toHaveBeenNthCalledWith(1, "/api/manager/order-requests/5/take", undefined);
    expect(mocks.post).toHaveBeenNthCalledWith(2, "/api/manager/order-requests/5/resolve", { manager_comment: "Выполнено" });
  });

  it("отправляет код нарушения отдельно от комментария", async () => {
    mocks.post.mockResolvedValue({ data: { message: "ok", data: {} } });
    await hideManagerReview(9, "spam", "Дублирующая реклама");
    expect(mocks.post).toHaveBeenCalledWith("/api/manager/reviews/9/hide", {
      reason_code: "spam",
      comment: "Дублирующая реклама",
    });
  });
});
