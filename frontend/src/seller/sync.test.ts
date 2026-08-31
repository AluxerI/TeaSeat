import { beforeEach, describe, expect, it, vi } from "vitest";
import type { SyncEventPayload } from "./api";
import type { ApiSellerOrder } from "./types";

const { postSyncMock } = vi.hoisted(() => ({ postSyncMock: vi.fn() }));
vi.mock("./api", () => ({ postSync: postSyncMock }));

import { db, resetSellerDatabase } from "./db";
import { commitOrder, emptyOrder, enqueueCommand, saveDraft } from "./orders";
import { drainOnce } from "./sync";
import type { LocalOrderItem } from "./types";

beforeEach(async () => {
  await resetSellerDatabase();
  postSyncMock.mockReset();
});

function line(overrides: Partial<LocalOrderItem> = {}): LocalOrderItem {
  return {
    product_id: 1,
    quantity: 30,
    pricing_token: "tok",
    name: "Улун",
    unit_price: 250,
    price_unit_quantity: 100,
    stock_unit: "gram",
    sale_step: 10,
    ...overrides,
  };
}

async function queuedOrder(id = "ord-1", warehouseId = 1) {
  const order = emptyOrder(warehouseId, new Date().toISOString());
  order.client_order_id = id;
  order.payment_method = "cash";
  order.items = [line()];
  await db.orders.put(order);
  await saveDraft(id, { items: [line()], payment_method: "cash" });
  await commitOrder(id);
}

function serverOrder(clientOrderId: string, revision = 1): ApiSellerOrder {
  return {
    id: 42,
    order_number: "S-1001",
    client_order_id: clientOrderId,
    revision,
    sales_channel: "seller",
    status: "pending",
    status_name: "Ожидает",
    payment_method: "cash",
    was_edited: false,
    actions: { can_edit: true, can_cancel: true, can_complete: true, can_escalate: false },
    totals: { products_total: 75, promotion_discount: 0, final_total: 75, currency: "RUB" },
    timestamps: { created_at: new Date().toISOString() },
  };
}

const okResponse = (events: Array<{ event_id: string; client_order_id?: string }>) => ({
  server_time: new Date().toISOString(),
  results: events.map((e) => ({
    event_id: e.event_id,
    result: "accepted" as const,
    order: serverOrder(e.client_order_id ?? "", 1),
  })),
});

describe("drainOnce", () => {
  it("отправляет upsert и применяет ответ сервера", async () => {
    await queuedOrder("ord-a");
    postSyncMock.mockImplementation(async (events: SyncEventPayload[]) => okResponse(events));

    const summary = await drainOnce("ord-a");

    expect(summary).toEqual({ sent: 1, applied: 1, stuck: 0 });
    expect(postSyncMock).toHaveBeenCalledTimes(1);
    const payload = postSyncMock.mock.calls[0][0];
    expect(payload).toHaveLength(1);
    expect(payload[0].action).toBe("upsert");
    expect(payload[0].revision).toBe(1);
    expect(payload[0].client_order_id).toBe("ord-a");
    expect(payload[0].items).toEqual([{ product_id: 1, quantity: 30, pricing_token: "tok" }]);

    expect(await db.outbox.count()).toBe(0);
    const order = await db.orders.get("ord-a");
    expect(order?.status).toBe("synced");
    expect(order?.server_id).toBe(42);
    expect(order?.acked_revision).toBe(1);
    expect(order?.order_number).toBe("S-1001");
    expect(order?.actions?.can_complete).toBe(true);
  });

  it("upsert уходит с версией acked_revision + 1", async () => {
    const order = emptyOrder(1, new Date().toISOString());
    order.client_order_id = "ord-acked";
    order.payment_method = "cash";
    order.items = [line()];
    order.acked_revision = 3;
    order.revision = 4;
    order.server_id = 99;
    order.status = "synced";
    await db.orders.put(order);
    await db.outbox.add({
      event_id: "ev-1",
      client_order_id: "ord-acked",
      action: "upsert",
      state: "pending",
      revision: 1,
      created_at: new Date().toISOString(),
      attempts: 0,
      last_error: null,
    });

    postSyncMock.mockImplementation(async (events: SyncEventPayload[]) => okResponse(events));
    await drainOnce("ord-acked");
    expect(postSyncMock.mock.calls[0][0][0].revision).toBe(4);
  });

  it("rejected → статус rejected с last_error", async () => {
    await queuedOrder("ord-rej");
    postSyncMock.mockImplementation(async (events: SyncEventPayload[]) => ({
      server_time: new Date().toISOString(),
      results: events.map((e) => ({
        event_id: e.event_id,
        result: "rejected" as const,
        message: "Пропуск версии",
        errors: { revision: ["must be sequential"] },
      })),
    }));

    const summary = await drainOnce("ord-rej");
    expect(summary.applied).toBe(1);

    const order = await db.orders.get("ord-rej");
    expect(order?.status).toBe("rejected");
    expect(order?.last_error).toBe("Пропуск версии");
    expect(order?.last_error_fields?.revision).toEqual(["must be sequential"]);
    expect(await db.outbox.count()).toBe(0);
  });

  it("сетевая ошибка возвращает события в pending и увеличивает attempts", async () => {
    await queuedOrder("ord-off");
    postSyncMock.mockRejectedValue(new Error("Нет связи с сервером"));

    const summary = await drainOnce("ord-off");
    expect(summary).toEqual({ sent: 1, applied: 0, stuck: 1 });

    const events = await db.outbox.toArray();
    expect(events).toHaveLength(1);
    expect(events[0].state).toBe("pending");
    expect(events[0].attempts).toBe(1);
    expect(events[0].last_error).toBe("Нет связи с сервером");
  });

  it("после upsert отправляет накопленную команду отдельным проходом", async () => {
    await queuedOrder("ord-local");
    await enqueueCommand("ord-local", "complete");
    postSyncMock.mockImplementation(async (events: SyncEventPayload[]) => okResponse(events));

    const summary = await drainOnce("ord-local");
    expect(summary).toEqual({ sent: 2, applied: 2, stuck: 0 });
    expect(postSyncMock).toHaveBeenCalledTimes(2);
    expect(postSyncMock.mock.calls[0][0][0].action).toBe("upsert");
    expect(postSyncMock.mock.calls[1][0][0].action).toBe("complete");
    expect(postSyncMock.mock.calls[1][0][0].order_id).toBe(42);
    expect(postSyncMock.mock.calls[1][0][0].revision).toBe(1);
    expect(await db.outbox.count()).toBe(0);
  });

  it("не теряет команду, если server_id ещё получить неоткуда", async () => {
    const order = emptyOrder(1, new Date().toISOString());
    order.client_order_id = "ord-command-only";
    await db.orders.put(order);
    await enqueueCommand(order.client_order_id, "cancel");

    const summary = await drainOnce(order.client_order_id);

    expect(summary).toEqual({ sent: 0, applied: 0, stuck: 1 });
    expect(postSyncMock).not.toHaveBeenCalled();
    expect(await db.outbox.count()).toBe(1);
  });

  it("возвращает оставшийся после перезапуска inflight в очередь", async () => {
    await queuedOrder("ord-inflight");
    const event = await db.outbox.where("client_order_id").equals("ord-inflight").first();
    await db.outbox.update(event!.event_id, { state: "inflight" });
    postSyncMock.mockImplementation(async (events: SyncEventPayload[]) => okResponse(events));

    const summary = await drainOnce("ord-inflight");

    expect(summary).toEqual({ sent: 1, applied: 1, stuck: 0 });
    expect(await db.outbox.count()).toBe(0);
  });

  it("команда уходит с версией acked_revision и order_id", async () => {
    const order = emptyOrder(1, new Date().toISOString());
    order.client_order_id = "ord-cmd";
    order.payment_method = "cash";
    order.items = [line()];
    order.server_id = 77;
    order.acked_revision = 3;
    order.revision = 3;
    order.status = "synced";
    await db.orders.put(order);
    await enqueueCommand("ord-cmd", "complete");

    postSyncMock.mockImplementation(async (events: SyncEventPayload[]) => ({
      server_time: new Date().toISOString(),
      results: events.map((e) => ({
        event_id: e.event_id,
        result: "accepted" as const,
        order: serverOrder("ord-cmd", 3),
      })),
    }));

    const summary = await drainOnce("ord-cmd");
    expect(summary.applied).toBe(1);
    expect(postSyncMock.mock.calls[0][0]).toHaveLength(1);
    const sent = postSyncMock.mock.calls[0][0][0];
    expect(sent.action).toBe("complete");
    expect(sent.order_id).toBe(77);
    expect(sent.revision).toBe(3);
    expect(await db.outbox.count()).toBe(0);
  });

  it("фильтрует очередь по client_order_id", async () => {
    await queuedOrder("ord-a");
    await queuedOrder("ord-b");
    postSyncMock.mockImplementation(async (events: SyncEventPayload[]) => okResponse(events));

    const summary = await drainOnce("ord-a");
    expect(summary.sent).toBe(1);

    const rest = await db.outbox.where("client_order_id").equals("ord-b").toArray();
    expect(rest).toHaveLength(1);
  });

  it("пустая очередь не ходит на сервер", async () => {
    const summary = await drainOnce();
    expect(summary).toEqual({ sent: 0, applied: 0, stuck: 0 });
    expect(postSyncMock).not.toHaveBeenCalled();
  });
});
