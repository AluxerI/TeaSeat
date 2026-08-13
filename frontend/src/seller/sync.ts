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
 * Команды без `server_id` (заказ ещё не синхронизирован) ждут следующего
 * прохода. Сначала уходит upsert, затем команда с полученным id и revision.
 *
 * Перед отправкой события помечаются `inflight`, чтобы параллельный дренаж
 * не отправил их второй раз. При сетевой ошибке они возвращаются в `pending`
 * с увеличенным `attempts` — повторяет вызывающий.
 */
let drainTail: Promise<void> = Promise.resolve();

/** Сериализует дренажи внутри вкладки. Без этого две кнопки синхронизации
 * успевали прочитать один pending event до перевода его в inflight. */
export function drainOnce(clientOrderId?: string): Promise<DrainSummary> {
  const run = drainTail.then(async () => {
    if ("locks" in navigator) {
      return await navigator.locks.request("teaseat-seller-sync", async () =>
        await drainPending(clientOrderId)
      );
    }
    return await drainPending(clientOrderId);
  });
  drainTail = run.then(
    () => undefined,
    () => undefined
  );
  return run;
}

async function drainPending(clientOrderId?: string): Promise<DrainSummary> {
  const summary: DrainSummary = { sent: 0, applied: 0, stuck: 0 };

  // Вкладка могла закрыться после claim, но до ответа. При следующем запуске
  // такие события должны снова стать отправляемыми, а не зависнуть навсегда.
  const abandoned = (await db.outbox.where("state").equals("inflight").toArray())
    .filter((event) => !clientOrderId || event.client_order_id === clientOrderId);
  if (abandoned.length > 0) {
    await db.outbox.bulkPut(
      abandoned.map((event) => ({ ...event, state: "pending" as const }))
    );
  }

  while (true) {
    const allPending = await db.outbox
      .where("state")
      .equals("pending")
      .sortBy("created_at");
    const pending = clientOrderId
      ? allPending.filter((event) => event.client_order_id === clientOrderId)
      : allPending;

    if (pending.length === 0) return summary;

    const clientOrderIds = [...new Set(pending.map((event) => event.client_order_id))];
    const orders = await db.orders.bulkGet(clientOrderIds);
    const orderMap = new Map(
      orders
        .filter((order): order is NonNullable<typeof order> => order !== undefined)
        .map((order) => [order.client_order_id, order])
    );

    // Для каждого заказа сначала всегда отправляем полный upsert. Команда,
    // накопленная офлайн, уйдёт следующим проходом уже с server_id и новой
    // acked_revision. API принимает не более 100 событий за запрос.
    const selected: OutboxEvent[] = [];
    for (const id of clientOrderIds) {
      const own = pending.filter((event) => event.client_order_id === id);
      selected.push(own.find((event) => event.action === "upsert") ?? own[0]);
    }

    const sendable = selected
      .filter((event) => {
        const order = orderMap.get(event.client_order_id);
        if (!order) return false;
        if (event.action === "upsert") return true;
        return order.server_id !== null && order.status !== "rejected";
      })
      .slice(0, 100);

    if (sendable.length === 0) {
      summary.stuck = pending.length;
      return summary;
    }

    const events: SyncEventPayload[] = sendable.map((event) => {
      const order = orderMap.get(event.client_order_id)!;
      const revision =
        event.action === "upsert"
          ? order.acked_revision + 1
          : order.acked_revision;

      if (event.action === "upsert") {
        return {
          event_id: event.event_id,
          action: "upsert",
          client_order_id: event.client_order_id,
          revision,
          warehouse_id: order.warehouse_id,
          occurred_at: order.occurred_at,
          payment_method: order.payment_method ?? undefined,
          items: order.items.map((item) => ({
            product_id: item.product_id,
            quantity: item.quantity,
            pricing_token: item.pricing_token,
          })),
        };
      }

      return {
        event_id: event.event_id,
        action: event.action,
        order_id: order.server_id!,
        revision,
      };
    });

    await db.outbox.bulkPut(
      sendable.map((event) => ({ ...event, state: "inflight" as const }))
    );
    summary.sent += events.length;

    let response;
    try {
      response = await postSync(events);
    } catch (error) {
      await db.outbox.bulkPut(
        sendable.map((event) => ({
          ...event,
          state: "pending" as const,
          attempts: event.attempts + 1,
          last_error:
            error instanceof Error ? error.message : "Нет связи с сервером",
        }))
      );
      summary.stuck = pending.length;
      return summary;
    }

    const answered = new Set<string>();
    for (const result of response.results) {
      answered.add(result.event_id);
      await applyResult(result);
    }
    summary.applied += answered.size;

    const unmatched = sendable.filter((event) => !answered.has(event.event_id));
    if (unmatched.length > 0) {
      await db.outbox.bulkPut(
        unmatched.map((event) => ({ ...event, state: "pending" as const }))
      );
      summary.stuck = pending.length - answered.size;
      return summary;
    }
  }
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
