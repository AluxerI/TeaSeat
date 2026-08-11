/** Слой запросов к серверу для сборщика.
 *
 *  Все маршруты — из backend/routes/api.php (требуют auth:sanctum).
 *  Используем тот же axios-инстанс, что и продавец (`../api/api`), где уже
 *  настроены Bearer-токен, withCredentials и CSRF.
 *  Ошибки разбираем тем же маппером `toSellerError`: 403/404/409/422 уже
 *  превращаются в понятные человеку сообщения.
 */

import { api } from "../api/api";
import { toSellerError } from "../seller/api";
import type {
  FulfillmentIssue,
  PickerListResponse,
  PickerOrder,
  PickerQueueFilters,
} from "./types";

interface PickerItemResponse {
  data: PickerOrder;
}

/** Общая обёртка для запросов, которые возвращают ОДИН заказ.
 *  Сервер отвечает `{ data: { data: PickerOrder } }` — разворачиваем в PickerOrder.
 *  Ошибку переводим в SellerApiError (знакомый тип ошибок из модуля продавца). */
async function unwrapOrder(
  promise: Promise<{ data: PickerItemResponse | { data: PickerOrder } }>
): Promise<PickerOrder> {
  try {
    const res = await promise;
    return res.data.data;
  } catch (error) {
    throw toSellerError(error);
  }
}

/** GET /api/picker/orders — список заданий сборщика.
 *  `mine: true` — только заказы текущего пользователя (таб «Моя работа»). */
export async function fetchQueue(
  filters: PickerQueueFilters = {}
): Promise<PickerListResponse> {
  const params: Record<string, string | number> = {
    per_page: filters.per_page ?? 50,
  };
  if (filters.status) params.status = filters.status;
  if (filters.warehouse_id) params.warehouse_id = filters.warehouse_id;
  if (filters.job_type) params.job_type = filters.job_type;
  if (filters.mine) params.mine = "1";

  try {
    const res = await api.get<PickerListResponse>("/api/picker/orders", {
      params,
    });
    return res.data;
  } catch (error) {
    throw toSellerError(error);
  }
}

/** GET /api/picker/orders/{id} — детали одного заказа. */
export async function fetchOrder(orderId: number): Promise<PickerOrder> {
  return unwrapOrder(
    api.get<PickerItemResponse>(`/api/picker/orders/${orderId}`)
  );
}

// Ниже — действия сборщика над заказом. Все они POST, потому что меняют
// состояние на сервере. Каждый возвращает обновлённый заказ.

/** Взять заказ в работу. */
export async function takeOrder(orderId: number): Promise<PickerOrder> {
  return unwrapOrder(api.post<PickerItemResponse>(`/api/picker/orders/${orderId}/take`));
}

/** Вернуть заказ обратно в очередь. */
export async function releaseOrder(orderId: number): Promise<PickerOrder> {
  return unwrapOrder(api.post<PickerItemResponse>(`/api/picker/orders/${orderId}/release`));
}

/** Завершить сборку (заказ готов к доставке). */
export async function completeOrder(orderId: number): Promise<PickerOrder> {
  return unwrapOrder(api.post<PickerItemResponse>(`/api/picker/orders/${orderId}/complete`));
}

/** Передать заказ менеджеру. Комментарий обязателен — зачем передаём. */
export async function escalateOrder(
  orderId: number,
  comment: string
): Promise<PickerOrder> {
  return unwrapOrder(
    api.post<PickerItemResponse>(`/api/picker/orders/${orderId}/escalate`, {
      comment,
    })
  );
}

/** Сообщить о недостаче. Отдаёт обновлённый заказ + созданный инцидент
 *  (его потом разбирает менеджер). */
export async function reportShortage(
  orderId: number,
  payload: {
    product_id: number;
    shortage_quantity: number;
    comment?: string;
  }
): Promise<{ order: PickerOrder; fulfillment_issue: FulfillmentIssue }> {
  try {
    const res = await api.post<{
      data: PickerOrder;
      fulfillment_issue: FulfillmentIssue;
    }>(`/api/picker/orders/${orderId}/shortage`, payload);
    return {
      order: res.data.data,
      fulfillment_issue: res.data.fulfillment_issue,
    };
  } catch (error) {
    throw toSellerError(error);
  }
}

/** GET /api/picker/incoming-transfers — трансферы, которые ждут нашего склада. */
export async function fetchIncomingTransfers(
  perPage = 50
): Promise<PickerListResponse> {
  try {
    const res = await api.get<PickerListResponse>("/api/picker/incoming-transfers", {
      params: { per_page: perPage },
    });
    return res.data;
  } catch (error) {
    throw toSellerError(error);
  }
}

/** Принять входящий трансфер (товар доехал и пришёл на наш склад). */
export async function receiveTransfer(orderId: number): Promise<PickerOrder> {
  return unwrapOrder(
    api.post<PickerItemResponse>(`/api/picker/incoming-transfers/${orderId}/receive`)
  );
}
