import { beforeEach, describe, expect, it } from "vitest";
import { db, resetSellerDatabase } from "./db";
import {
  commitOrder,
  createDraft,
  deleteDraft,
  emptyOrder,
  enqueueCommand,
  enqueueDayClosing,
  reopenForEdit,
  removeItem,
  saveDraft,
  upsertItem,
} from "./orders";
import type { LocalOrderItem, LocalProduct } from "./types";

beforeEach(async () => {
  await resetSellerDatabase();
});

describe("db.products (составной ключ [warehouse_id+id])", () => {
  it("bulkPut товара с полем id не падает с DataError", async () => {
    const product: LocalProduct = {
      id: 1,
      name: "Улун",
      stock_unit: "gram",
      sale_step: 10,
      price_unit_quantity: 100,
      pricing: {
        unit_price: 250,
        issued_at: "2026-01-01T00:00:00Z",
        expires_at: "2026-02-01T00:00:00Z",
        automatic_promotions: [],
      },
      pricing_token: "tok",
      stock: {
        quantity: 100,
        reserved_online_quantity: 0,
        reserved_seller_quantity: 0,
        available_quantity: 100,
        shortage_quantity: 0,
      },
      warehouse_id: 1,
      image: null,
    };

    await expect(
      db.products.bulkPut([product, { ...product, id: 2 }])
    ).resolves.toEqual([1, 2]);

    const cached = await db.products
      .where("warehouse_id")
      .equals(1)
      .toArray();
    expect(cached.map((p) => p.id).sort()).toEqual([1, 2]);
  });
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

describe("createDraft / emptyOrder", () => {
  it("создаёт черновик в состоянии draft", async () => {
    const draft = await createDraft(1);
    expect(draft.client_order_id).toMatch(/^[0-9a-f-]{36}$/);
    expect(draft.warehouse_id).toBe(1);
    expect(draft.status).toBe("draft");
    expect(draft.revision).toBe(1);
    expect(draft.acked_revision).toBe(0);
    expect(draft.payment_method).toBeNull();
    expect(draft.items).toEqual([]);
    expect(draft.server_id).toBeNull();
    expect(await db.orders.get(draft.client_order_id)).toBeDefined();
  });

  it("emptyOrder фиксирует переданное время продажи", () => {
    const order = emptyOrder(2, "2026-01-02T10:00:00.000Z");
    expect(order.occurred_at).toBe("2026-01-02T10:00:00.000Z");
    expect(order.warehouse_id).toBe(2);
  });
});

describe("saveDraft", () => {
  it("сохраняет позиции и пересчитывает totals_preview", async () => {
    const draft = await createDraft(1);
    const saved = await saveDraft(draft.client_order_id, {
      items: [line()],
      payment_method: "cash",
      customer_note: "без сахара",
    });
    expect(saved.items).toHaveLength(1);
    expect(saved.payment_method).toBe("cash");
    expect(saved.customer_note).toBe("без сахара");
    expect(saved.totals_preview).toBe(75);

    const fromDb = await db.orders.get(draft.client_order_id);
    expect(fromDb?.items).toHaveLength(1);
  });

  it("бросает ошибку, если заказ не найден", async () => {
    await expect(saveDraft("missing", { items: [line()] })).rejects.toThrow();
  });
});

describe("upsertItem / removeItem", () => {
  it("добавляет новую позицию и заменяет существующую", () => {
    let items = upsertItem([], line({ product_id: 1 }));
    items = upsertItem(items, line({ product_id: 1, quantity: 60 }));
    expect(items).toHaveLength(1);
    expect(items[0].quantity).toBe(60);
  });

  it("removeItem убирает только нужный товар", () => {
    const items = [line({ product_id: 1 }), line({ product_id: 2 })];
    const next = removeItem(items, 1);
    expect(next.map((i) => i.product_id)).toEqual([2]);
  });
});

describe("commitOrder", () => {
  it("требует позиции и способ оплаты", async () => {
    const empty = await createDraft(1);
    await expect(commitOrder(empty.client_order_id)).rejects.toThrow("нет позиций");

    const noPayment = await createDraft(1);
    await saveDraft(noPayment.client_order_id, { items: [line()] });
    await expect(commitOrder(noPayment.client_order_id)).rejects.toThrow("способ оплаты");
  });

  it("ставит заказ в очередь и создаёт один pending upsert", async () => {
    const draft = await createDraft(1);
    await saveDraft(draft.client_order_id, { items: [line()], payment_method: "cash" });

    const committed = await commitOrder(draft.client_order_id);
    expect(committed.status).toBe("queued");
    expect(committed.revision).toBe(1);

    const events = await db.outbox.toArray();
    expect(events).toHaveLength(1);
    expect(events[0].action).toBe("upsert");
    expect(events[0].state).toBe("pending");
    expect(events[0].client_order_id).toBe(draft.client_order_id);
  });

  it("повторный коммит заменяет upsert, а не плодит второй", async () => {
    const draft = await createDraft(1);
    await saveDraft(draft.client_order_id, { items: [line()], payment_method: "cash" });
    await commitOrder(draft.client_order_id);
    await commitOrder(draft.client_order_id);

    expect(await db.outbox.count()).toBe(1);
  });
});

describe("enqueueCommand", () => {
  it("ставит команду в очередь и помечает заказ queued", async () => {
    const draft = await createDraft(1);
    const event = await enqueueCommand(draft.client_order_id, "cancel");
    expect(event.action).toBe("cancel");
    expect(event.state).toBe("pending");
    expect(event.revision).toBe(draft.acked_revision);

    const order = await db.orders.get(draft.client_order_id);
    expect(order?.status).toBe("queued");
  });

  it("не дублирует одинаковую pending-команду", async () => {
    const draft = await createDraft(1);
    await enqueueCommand(draft.client_order_id, "complete");
    await enqueueCommand(draft.client_order_id, "complete");
    const events = await db.outbox.toArray();
    expect(events).toHaveLength(1);
  });

  it("бросает ошибку для несуществующего заказа", async () => {
    await expect(enqueueCommand("missing", "cancel")).rejects.toThrow();
  });
});

describe("enqueueDayClosing", () => {
  it("проводит только серверные заказы с can_complete", async () => {
    const draft = await createDraft(1);
    await db.orders.update(draft.client_order_id, {
      server_id: 42,
      acked_revision: 1,
      status: "synced",
      server_status: "pending",
      order_number: "S-1",
      actions: { can_edit: true, can_cancel: true, can_complete: true, can_escalate: false },
    });

    const second = await createDraft(1);
    await db.orders.update(second.client_order_id, {
      server_id: 43,
      acked_revision: 1,
      status: "synced",
      actions: { can_edit: false, can_cancel: false, can_complete: false, can_escalate: false },
    });

    const count = await enqueueDayClosing();
    expect(count).toBe(1);
    const events = await db.outbox.toArray();
    expect(events).toHaveLength(1);
    expect(events[0].action).toBe("complete");
    expect(events[0].client_order_id).toBe(draft.client_order_id);
  });

  it("возвращает 0, если проводить нечего", async () => {
    await createDraft(1);
    expect(await enqueueDayClosing()).toBe(0);
  });
});

describe("reopenForEdit / deleteDraft", () => {
  it("reopenForEdit возвращает заказ в draft", async () => {
    const draft = await createDraft(1);
    await saveDraft(draft.client_order_id, { items: [line()], payment_method: "cash" });
    await commitOrder(draft.client_order_id);

    const reopened = await reopenForEdit(draft.client_order_id);
    expect(reopened.status).toBe("draft");
  });

  it("deleteDraft удаляет чистый черновик", async () => {
    const draft = await createDraft(1);
    await deleteDraft(draft.client_order_id);
    expect(await db.orders.get(draft.client_order_id)).toBeUndefined();
  });

  it("deleteDraft запрещён, если заказ уже в очереди", async () => {
    const draft = await createDraft(1);
    await saveDraft(draft.client_order_id, { items: [line()], payment_method: "cash" });
    await commitOrder(draft.client_order_id);

    await expect(deleteDraft(draft.client_order_id)).rejects.toThrow("в очереди");
  });
});
