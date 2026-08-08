import { db } from "./db";
import { serverNow } from "./api";
import { previewOrderTotal } from "./quantity";
import type {
  LocalOrder,
  LocalOrderItem,
  OutboxAction,
  OutboxEvent,
  PaymentMethod,
} from "./types";

/** Создание, правка и постановка команд заказа в очередь.
 *
 *  Инвариант, вокруг которого всё построено: версия события вычисляется не
 *  здесь, а в момент отправки (`sync.ts`), из `acked_revision` заказа.
 *  Иначе две офлайн-правки дали бы версии 2 и 3, из которых сервер увидел бы
 *  только вторую — и отклонил её как пропуск версии. */

export function emptyOrder(
  warehouseId: number,
  occurredAt: string
): LocalOrder {
  const now = new Date().toISOString();
  return {
    client_order_id: crypto.randomUUID(),
    server_id: null,
    revision: 1,
    acked_revision: 0,
    warehouse_id: warehouseId,
    occurred_at: occurredAt,
    payment_method: null,
    items: [],
    status: "draft",
    server_status: null,
    server_status_name: null,
    order_number: null,
    actions: null,
    conflicts: [],
    last_error: null,
    last_error_fields: null,
    was_edited: false,
    customer_note: null,
    totals_preview: 0,
    created_at: now,
    updated_at: now,
  };
}

/** Новый черновик. `occurred_at` — момент физической продажи по серверным
 *  часам: именно он важен backend, а не момент поздней синхронизации. */
export async function createDraft(warehouseId: number): Promise<LocalOrder> {
  const occurredAt = (await serverNow()).toISOString();
  const draft = emptyOrder(warehouseId, occurredAt);
  await db.orders.put(draft);
  return draft;
}

export async function getOrder(clientOrderId: string): Promise<LocalOrder | undefined> {
  return db.orders.get(clientOrderId);
}

/** Сохранение черновика на каждом шаге визарда: разряженный планшет не должен
 *  стоить продавцу набранной корзины. */
export async function saveDraft(
  clientOrderId: string,
  patch: Partial<Pick<LocalOrder, "items" | "payment_method" | "customer_note">>
): Promise<LocalOrder> {
  return db.transaction("rw", db.orders, async () => {
    const order = await db.orders.get(clientOrderId);
    if (!order) throw new Error(`Заказ ${clientOrderId} не найден`);
    const next: LocalOrder = {
      ...order,
      ...patch,
      updated_at: new Date().toISOString(),
    };
    next.totals_preview = previewOrderTotal(next.items);
    await db.orders.put(next);
    return next;
  });
}

export function upsertItem(
  items: LocalOrderItem[],
  item: LocalOrderItem
): LocalOrderItem[] {
  const index = items.findIndex((i) => i.product_id === item.product_id);
  if (index === -1) return [...items, item];
  const next = [...items];
  next[index] = item;
  return next;
}

export function removeItem(
  items: LocalOrderItem[],
  productId: number
): LocalOrderItem[] {
  return items.filter((i) => i.product_id !== productId);
}

/** Ставит в очередь `upsert`. Если непосланный `upsert` уже висит — заменяет
 *  его, а не добавляет второй: сервер принимает версии строго подряд. */
async function enqueueUpsert(clientOrderId: string): Promise<OutboxEvent> {
  const existing = await db.outbox
    .where("client_order_id")
    .equals(clientOrderId)
    .filter((e) => e.action === "upsert" && e.state === "pending")
    .first();

  if (existing) {
    const refreshed: OutboxEvent = {
      ...existing,
      created_at: new Date().toISOString(),
      last_error: null,
    };
    await db.outbox.put(refreshed);
    return refreshed;
  }

  const event: OutboxEvent = {
    event_id: crypto.randomUUID(),
    client_order_id: clientOrderId,
    action: "upsert",
    state: "pending",
    // Настоящая версия подставляется при отправке; здесь только заглушка,
    // чтобы очередь можно было показать до первого дренажа.
    revision: 1,
    created_at: new Date().toISOString(),
    attempts: 0,
    last_error: null,
  };
  await db.outbox.add(event);
  return event;
}

/** Финальный шаг визарда: черновик становится очередью на отправку. */
export async function commitOrder(clientOrderId: string): Promise<LocalOrder> {
  return db.transaction("rw", db.orders, db.outbox, async () => {
    const order = await db.orders.get(clientOrderId);
    if (!order) throw new Error(`Заказ ${clientOrderId} не найден`);
    if (order.items.length === 0) throw new Error("В заказе нет позиций");
    if (!order.payment_method) throw new Error("Не выбран способ оплаты");

    await enqueueUpsert(clientOrderId);
    const next: LocalOrder = {
      ...order,
      status: "queued",
      revision: order.acked_revision + 1,
      was_edited: order.acked_revision > 0 ? true : order.was_edited,
      last_error: null,
      last_error_fields: null,
      totals_preview: previewOrderTotal(order.items),
      updated_at: new Date().toISOString(),
    };
    await db.orders.put(next);
    return next;
  });
}

/** Правка уже отправленного (или ещё стоящего в очереди) заказа. */
export async function reopenForEdit(clientOrderId: string): Promise<LocalOrder> {
  return db.transaction("rw", db.orders, async () => {
    const order = await db.orders.get(clientOrderId);
    if (!order) throw new Error(`Заказ ${clientOrderId} не найден`);
    const next: LocalOrder = {
      ...order,
      status: "draft",
      updated_at: new Date().toISOString(),
    };
    await db.orders.put(next);
    return next;
  });
}

/** Команда над серверным заказом. Отправится только после того, как `upsert`
 *  вернёт серверный `id`, — порядок обеспечивает дренаж. */
export async function enqueueCommand(
  clientOrderId: string,
  action: Exclude<OutboxAction, "upsert">
): Promise<OutboxEvent> {
  return db.transaction("rw", db.orders, db.outbox, async () => {
    const order = await db.orders.get(clientOrderId);
    if (!order) throw new Error(`Заказ ${clientOrderId} не найден`);

    const duplicate = await db.outbox
      .where("client_order_id")
      .equals(clientOrderId)
      .filter((e) => e.action === action && e.state === "pending")
      .first();
    if (duplicate) return duplicate;

    const event: OutboxEvent = {
      event_id: crypto.randomUUID(),
      client_order_id: clientOrderId,
      action,
      state: "pending",
      revision: order.acked_revision,
      created_at: new Date().toISOString(),
      attempts: 0,
      last_error: null,
    };
    await db.outbox.add(event);
    await db.orders.put({
      ...order,
      status: "queued",
      last_error: null,
      updated_at: new Date().toISOString(),
    });
    return event;
  });
}

/** Завершение дня: провести всё, что сервер разрешает провести. */
export async function enqueueDayClosing(): Promise<number> {
  const orders = await db.orders.toArray();
  const completable = orders.filter(
    (o) => o.server_id !== null && (o.actions?.can_complete ?? false)
  );
  for (const order of completable) {
    await enqueueCommand(order.client_order_id, "complete");
  }
  return completable.length;
}

export async function deleteDraft(clientOrderId: string): Promise<void> {
  await db.transaction("rw", db.orders, db.outbox, async () => {
    const queued = await db.outbox
      .where("client_order_id")
      .equals(clientOrderId)
      .count();
    if (queued > 0) throw new Error("Заказ уже в очереди на синхронизацию");
    await db.orders.delete(clientOrderId);
  });
}
