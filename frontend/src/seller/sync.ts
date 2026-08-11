import { db } from "./db";
import { postSync, type SyncEventPayload } from "./api";
import type { ApiSellerOrder, ApiSyncResult, OutboxEvent } from "./types";

/** Результат одного дренажа очереди. `stuck` — события, которые остались
 *  ждать сети (офлайн / throttle / ошибка сервера). */
export interface DrainSummary {
  sent: number;
  applied: number;
  stuck: number;
}

/**
 * Дренаж outbox: берёт `pending` события, подставляет настоящую версию и
 * отправляет батчем на `POST /api/seller/sync`.
 *
 * Инвариант (см. `orders.ts`): версия события вычисляется здесь из
 * `acked_revision` заказа, а не при постановке в очередь. Иначе две
 * офлайн-правки дали бы версии 2 и 3, из которых сервер принял бы только
 * вторую и отклонил её как пропуск версии.
 *
 * - `upsert`  → revision = acked_revision + 1 (следующая версия содержимого);
 * - команды `cancel`/`complete`/`escalate` → revision = acked_revision
 *   (текущая принятая версия; команда версию не повышает).
 *
 * Команды без `server_id` (заказ ещё не синхронизирован) отправить нельзя —
 * такие события дропаются: их порядок гарантирует сам дренаж (upsert всегда
 * идёт первым из-за сортировки по времени создания и удаляется после успеха).
 *
 * Перед отправкой события помечаются `inflight`, чтобы параллельный дренаж
 * не отправил их второй раз. При сетевой ошибке они возвращаются в `pending`
 * с увеличенным `attempts` — повторяет вызывающий.
 */
export async function drainOnce(clientOrderId?: string): Promise<DrainSummary> {
  const pending = await db.outbox
    .where("state")
    .equals("pending")
    .sortBy("created_at");
  const batch = clientOrderId
    ? pending.filter((e) => e.client_order_id === clientOrderId)
    : pending;

  if (batch.length === 0) return { sent: 0, applied: 0, stuck: 0 };

  const clientOrderIds = [...new Set(batch.map((e) => e.client_order_id))];
  const orders = await db.orders.bulkGet(clientOrderIds);
  const orderMap = new Map(
    orders
      .filter((o): o is NonNullable<typeof o> => o !== undefined)
      .map((o) => [o.client_order_id, o])
  );

  const droppable: string[] = [];
  const events: SyncEventPayload[] = [];
  for (const event of batch) {
    const order = orderMap.get(event.client_order_id);
    if (event.action !== "upsert" && !order?.server_id) {
      // Заказ ещё не долетел до сервера — команда не имеет смысла.
      droppable.push(event.event_id);
      continue;
    }

    const revision =
      event.action === "upsert"
        ? (order?.acked_revision ?? 0) + 1
        : (order?.acked_revision ?? 0);

    if (event.action === "upsert") {
      events.push({
        event_id: event.event_id,
        action: "upsert",
        client_order_id: event.client_order_id,
        revision,
        warehouse_id: order?.warehouse_id,
        occurred_at: order?.occurred_at,
        payment_method: order?.payment_method ?? undefined,
        items: (order?.items ?? []).map((i) => ({
          product_id: i.product_id,
          quantity: i.quantity,
          pricing_token: i.pricing_token,
        })),
      });
    } else {
      events.push({
        event_id: event.event_id,
        action: event.action,
        order_id: order!.server_id!,
        revision,
      });
    }
  }

  if (droppable.length > 0) {
    await db.outbox.bulkDelete(droppable);
  }
  if (events.length === 0) return { sent: 0, applied: 0, stuck: 0 };

  const sentIds = new Set(events.map((e) => e.event_id));
  const inflight = batch.filter((e) => sentIds.has(e.event_id));
  await db.outbox.bulkPut(
    inflight.map((e) => ({ ...e, state: "inflight" as const }))
  );

  let response;
  try {
    response = await postSync(events);
  } catch (error) {
    await db.outbox.bulkPut(
      inflight.map((e) => ({
        ...e,
        state: "pending" as const,
        attempts: e.attempts + 1,
        last_error:
          error instanceof Error ? error.message : "Нет связи с сервером",
      }))
    );
    return { sent: events.length, applied: 0, stuck: events.length };
  }

  const answered = new Set<string>();
  for (const result of response.results) {
    answered.add(result.event_id);
    await applyResult(result);
  }

  const unmatched = inflight.filter((e) => !answered.has(e.event_id));
  if (unmatched.length > 0) {
    await db.outbox.bulkPut(unmatched.map((e) => ({ ...e, state: "pending" })));
  }

  return {
    sent: events.length,
    applied: answered.size,
    stuck: events.length - answered.size,
  };
}

/** Применяет один ответ сервера к локальному заказу и удаляет событие. */
async function applyResult(result: ApiSyncResult): Promise<void> {
  const event = await db.outbox.get(result.event_id);
  if (!event) return;

  const order = await db.orders.get(event.client_order_id);
  const serverOrder = result.order as ApiSellerOrder | undefined;

  if (result.result === "rejected") {
    if (order) {
      await db.orders.put({
        ...order,
        status: "rejected",
        last_error: result.message ?? "Операция отклонена сервером",
        last_error_fields: result.errors ?? null,
        updated_at: new Date().toISOString(),
      });
    }
    await db.outbox.delete(result.event_id);
    return;
  }

  if (order && serverOrder) {
    await db.orders.put({
      ...order,
      server_id: serverOrder.id,
      acked_revision: serverOrder.revision,
      server_status: serverOrder.status,
      server_status_name: serverOrder.status_name,
      order_number: serverOrder.order_number,
      actions: serverOrder.actions,
      was_edited: serverOrder.was_edited,
      status: "synced",
      conflicts: result.conflicts ?? [],
      last_error: null,
      last_error_fields: null,
      updated_at: new Date().toISOString(),
    });
  }

  await db.outbox.delete(result.event_id);
}

export type { OutboxEvent };
