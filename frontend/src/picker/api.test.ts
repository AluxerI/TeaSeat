import { AxiosError } from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
}));

vi.mock("../api/api", () => ({
  api: {
    get: mocks.get,
    post: mocks.post,
  },
}));

import {
  completeOrder,
  escalateOrder,
  fetchIncomingTransfers,
  fetchOrder,
  fetchQueue,
  receiveTransfer,
  releaseOrder,
  reportShortage,
  takeOrder,
} from "./api";
import type { PickerOrder } from "./types";

function order(overrides: Partial<PickerOrder> = {}): PickerOrder {
  return {
    id: 7,
    order_number: "P-0007",
    customer_order_id: 11,
    customer_order_number: "A-0011",
    job_type: "source",
    status: "confirmed",
    status_name: "Подтверждён",
    warehouse: { id: 1, name: "Флагман" },
    destination_warehouse: null,
    picker: null,
    items: [],
    gifts: [],
    customer_notes: null,
    timestamps: {
      created_at: "2026-01-01T10:00:00.000Z",
      picking_started_at: null,
      ready_for_delivery_at: null,
      courier_arrived_at: null,
      received_at: null,
    },
    actions: {
      can_take: true,
      can_release: false,
      can_complete: false,
      can_escalate: false,
      can_report_shortage: false,
      can_receive: false,
    },
    ...overrides,
  };
}

beforeEach(() => {
  mocks.get.mockReset();
  mocks.post.mockReset();
});

describe("fetchQueue", () => {
  it("передаёт фильтры параметрами запроса", async () => {
    const body = { data: [order()], meta: { current_page: 1, last_page: 1, per_page: 50, total: 1 } };
    mocks.get.mockResolvedValue({ data: body });

    const res = await fetchQueue({ warehouse_id: 1, mine: true, status: "confirmed" });

    expect(mocks.get).toHaveBeenCalledWith("/api/picker/orders", {
      params: {
        per_page: 50,
        warehouse_id: 1,
        mine: "1",
        status: "confirmed",
      },
    });
    expect(res.data[0].id).toBe(7);
  });
});

describe("fetchOrder", () => {
  it("возвращает data.data", async () => {
    mocks.get.mockResolvedValue({ data: { data: order({ status: "processing" }) } });

    const res = await fetchOrder(7);

    expect(mocks.get).toHaveBeenCalledWith("/api/picker/orders/7");
    expect(res.status).toBe("processing");
  });
});

describe("take / release / complete", () => {
  it("бьют в соответствующие эндпоинты", async () => {
    mocks.post.mockResolvedValue({ data: { data: order({ status: "processing" }) } });

    await takeOrder(7);
    await releaseOrder(7);
    await completeOrder(7);

    expect(mocks.post).toHaveBeenNthCalledWith(1, "/api/picker/orders/7/take");
    expect(mocks.post).toHaveBeenNthCalledWith(2, "/api/picker/orders/7/release");
    expect(mocks.post).toHaveBeenNthCalledWith(3, "/api/picker/orders/7/complete");
  });
});

describe("escalateOrder", () => {
  it("передаёт comment телом запроса", async () => {
    mocks.post.mockResolvedValue({ data: { data: order({ status: "confirmed" }) } });

    await escalateOrder(7, "Товар не сошёлся");

    expect(mocks.post).toHaveBeenCalledWith("/api/picker/orders/7/escalate", {
      comment: "Товар не сошёлся",
    });
  });
});

describe("reportShortage", () => {
  it("возвращает заказ и инцидент", async () => {
    mocks.post.mockResolvedValue({
      data: {
        data: order({ status: "confirmed" }),
        fulfillment_issue: {
          id: 1,
          source_order_id: 7,
          product_id: 3,
          warehouse_id: 1,
          reason: "shortage",
          reason_message: "Недостача",
          shortage_quantity: 2,
          status: "open",
          created_at: null,
          updated_at: null,
        },
      },
    });

    const res = await reportShortage(7, { product_id: 3, shortage_quantity: 2 });

    expect(mocks.post).toHaveBeenCalledWith("/api/picker/orders/7/shortage", {
      product_id: 3,
      shortage_quantity: 2,
    });
    expect(res.order.id).toBe(7);
    expect(res.fulfillment_issue.shortage_quantity).toBe(2);
  });
});

describe("fetchIncomingTransfers", () => {
  it("читает /api/picker/incoming-transfers", async () => {
    mocks.get.mockResolvedValue({
      data: { data: [order({ job_type: "consolidation" })], meta: { current_page: 1, last_page: 1, per_page: 50, total: 1 } },
    });

    const res = await fetchIncomingTransfers();

    expect(mocks.get).toHaveBeenCalledWith("/api/picker/incoming-transfers", {
      params: { per_page: 50 },
    });
    expect(res.data[0].job_type).toBe("consolidation");
  });
});

describe("receiveTransfer", () => {
  it("бьёт в receive-эндпоинт", async () => {
    mocks.post.mockResolvedValue({ data: { data: order({ status: "delivered" }) } });

    const res = await receiveTransfer(7);

    expect(mocks.post).toHaveBeenCalledWith("/api/picker/incoming-transfers/7/receive");
    expect(res.status).toBe("delivered");
  });
});

describe("ошибки", () => {
  it("422 превращается в SellerApiError kind=rejected", async () => {
    const err = new AxiosError(
      "Request failed",
      undefined,
      undefined,
      undefined,
      { status: 422, data: { message: "Заказ уже в работе", code: "already_taken" } } as never
    );
    mocks.post.mockRejectedValue(err);

    await expect(takeOrder(7)).rejects.toMatchObject({
      kind: "rejected",
      status: 422,
      code: "already_taken",
    });
  });
});
