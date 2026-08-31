// Импорт базового HTTP-клиента приложения (обёртка над axios).
import { api } from "../api/api";
// Функция преобразования ошибок API в человекочитаемый формат.
import { toStaffApiError } from "../staff/errors";
// Типы ответов API кабинета менеджера.
import type {
  AffectedOrdersResponse, // ответ со списком затронутых заказов
  CommandResponse, // ответ на команду (message + data)
  FeedbackFilters, // фильтры обратной связи по доставке
  FeedbackListResponse, // ответ списка обратной связи
  FulfillmentIssue, // проблема комплектации
  IssueFilters, // фильтры проблем
  IssueListResponse, // ответ списка проблем
  ItemCommandBase, // базовая часть тела команды изменения позиции
  ManagerFeedback, // обратная связь по заказу
  ManagerOrder, // заказ менеджера
  ManagerOrderFilters, // фильтры заказов
  ManagerOrderRequest, // обращение клиента
  ManagerReview, // отзыв на товар
  Paginated, // пагинированный ответ
  RequestFilters, // фильтры обращений
  RequestListResponse, // ответ списка обращений
  ReviewFilters, // фильтры отзывов
} from "./types";

// Базовый префикс всех endpoint'ов кабинета менеджера.
const MANAGER = "/api/manager";

// Отбрасывает пустые/undefined значения фильтров, чтобы не слать их в query.
function cleanParams(filters: object): Record<string, string | number | boolean> {
  // Превращаем объект в массив пар [ключ, значение]...
  return Object.fromEntries(
    // ...оставляем только те, где значение не undefined и не пустая строка.
    Object.entries(filters).filter(([, value]) => value !== undefined && value !== ""),
  );
}

// Обёртка запроса: выполняет запрос, достаёт `data` и переводит ошибки в понятный вид.
async function request<T>(call: () => Promise<{ data: T }>, entity: string): Promise<T> {
  try {
    // Выполняем переданный запрос и возвращаем его полезную нагрузку.
    return (await call()).data;
  } catch (error) {
    // Любая ошибка превращается в осмысленную StaffApiError с именем сущности.
    throw toStaffApiError(error, entity);
  }
}

// ── Заказы ──────────────────────────────────────────────────────────────────

// Получить страницу заказов по заданным фильтрам (по умолчанию без фильтров).
export function fetchManagerOrders(filters: ManagerOrderFilters = {}) {
  return request(
    // GET /api/manager/orders с очищенными query-параметрами фильтров.
    () => api.get<Paginated<ManagerOrder>>(`${MANAGER}/orders`, { params: cleanParams(filters) }),
    "Заказы", // имя сущности для сообщений об ошибке
  );
}

// Получить один заказ по id (детальное представление).
export async function fetchManagerOrder(id: number): Promise<ManagerOrder> {
  // Выполняем запрос; обёртка возвращает весь ответ, но здесь нам нужен его .data.
  const response = await request(
    () => api.get<{ data: ManagerOrder }>(`${MANAGER}/orders/${id}`),
    "Заказ",
  );
  // Достаём сам заказ из обёртки ответа.
  return response.data;
}

// Общий вызов команды над заказом (confirm/cancel/internal-notes и т.д.).
function orderCommand(id: number, action: string, body?: object) {
  return request(
    // POST на /api/manager/orders/{id}/{action} с опциональным телом.
    () => api.post<CommandResponse<ManagerOrder>>(`${MANAGER}/orders/${id}/${action}`, body),
    "Заказ",
  );
}

// Добавить внутреннюю заметку к заказу (комментарий обрезается по краям).
export const addManagerOrderNote = (id: number, comment: string) =>
  orderCommand(id, "internal-notes", { comment: comment.trim() });
// Подтвердить заказ (без тела).
export const confirmManagerOrder = (id: number) => orderCommand(id, "confirm");
// Отменить заказ с указанием причины.
export const cancelManagerOrder = (id: number, reason: string) =>
  orderCommand(id, "cancel", { reason: reason.trim() });
// Перенести доставку: новая дата, слот времени и причина.
export const rescheduleManagerOrder = (
  id: number,
  scheduled_delivery_date: string,
  delivery_time_slot_id: number,
  reason: string,
) => orderCommand(id, "reschedule", { scheduled_delivery_date, delivery_time_slot_id, reason: reason.trim() });

// Вернуть товар заказа на склад (при отмене/списании) с указанием причины.
export const returnManagerOrderToStock = (id: number, reason: string) =>
  request(
    () => api.post<{ message: string }>(`${MANAGER}/orders/${id}/return-to-stock`, { reason: reason.trim() }),
    "Заказ",
  );

// Назначить курьера на доставку заказа.
export const assignManagerCourier = (id: number, courierId: number) =>
  request(
    () => api.post<{ message: string }>(`${MANAGER}/deliveries/${id}/assign-courier`, { courier_id: courierId }),
    "Доставка",
  );

// Сгенерировать уникальный id операции для идемпотентности команд изменения состава.
export function newOperationId(): string {
  return crypto.randomUUID();
}

// Ответ команды по позиции дополнительно сообщает, применялась ли она ранее.
type ItemCommandResponse = CommandResponse<ManagerOrder> & { already_applied: boolean };
// Запрос по позиции/набору заказа; сущность "Позиция заказа" для ошибок.
const itemRequest = (call: () => Promise<{ data: ItemCommandResponse }>) => request(call, "Позиция заказа");

// Добавить новую позицию в заказ (товар + количество).
export const addManagerOrderItem = (
  orderId: number,
  payload: ItemCommandBase & { product_id: number; quantity: number },
) => itemRequest(() => api.post(`${MANAGER}/orders/${orderId}/items`, payload));

// Изменить количество существующей позиции (PATCH).
export const changeManagerOrderItem = (
  orderId: number,
  itemId: number,
  payload: ItemCommandBase & { quantity: number },
) => itemRequest(() => api.patch(`${MANAGER}/orders/${orderId}/items/${itemId}`, payload));

// Заменить позицию на другой товар.
export const replaceManagerOrderItem = (
  orderId: number,
  itemId: number,
  payload: ItemCommandBase & { product_id: number; quantity: number },
) => itemRequest(() => api.post(`${MANAGER}/orders/${orderId}/items/${itemId}/replace`, payload));

// Удалить позицию из заказа.
export const removeManagerOrderItem = (orderId: number, itemId: number, payload: ItemCommandBase) =>
  itemRequest(() => api.post(`${MANAGER}/orders/${orderId}/items/${itemId}/remove`, payload));

// Удалить подарочный набор из заказа.
export const removeManagerOrderGift = (orderId: number, giftId: number, payload: ItemCommandBase) =>
  itemRequest(() => api.post(`${MANAGER}/orders/${orderId}/gifts/${giftId}/remove`, payload));

// Заменить подарочный набор другим набором (новая версия и количество).
export const replaceManagerOrderGift = (
  orderId: number,
  giftId: number,
  payload: ItemCommandBase & { gift_id: number; gift_version: number; quantity: number },
) => itemRequest(() => api.post(`${MANAGER}/orders/${orderId}/gifts/${giftId}/replace`, payload));

// ── Проблемы складского исполнения ──────────────────────────────────────────

// Получить страницу проблем комплектации с фильтрами.
export const fetchFulfillmentIssues = (filters: IssueFilters = {}) =>
  request(
    () => api.get<IssueListResponse>(`${MANAGER}/fulfillment-issues`, { params: cleanParams(filters) }),
    "Проблемы комплектации",
  );

// Получить одну проблему по id.
export async function fetchFulfillmentIssue(id: number): Promise<FulfillmentIssue> {
  // Запрос на детальную проблему.
  const response = await request(
    () => api.get<{ data: FulfillmentIssue }>(`${MANAGER}/fulfillment-issues/${id}`),
    "Проблема комплектации",
  );
  // Достаём саму проблему из обёртки.
  return response.data;
}

// Получить заказы, затронутые проблемой (страница по 20 записей).
export const fetchAffectedOrders = (id: number, page = 1) =>
  request(
    () => api.get<AffectedOrdersResponse>(`${MANAGER}/fulfillment-issues/${id}/affected-orders`, {
      params: { page, per_page: 20 }, // фиксированный размер страницы
    }),
    "Связанные заказы",
  );

// Сменить статус проблемы: взять в работу / отпустить / закрыть.
export const transitionFulfillmentIssue = (id: number, action: "take" | "release" | "close") =>
  request(
    () => api.post<CommandResponse<FulfillmentIssue>>(`${MANAGER}/fulfillment-issues/${id}/${action}`),
    "Проблема комплектации",
  );

// ── Обращения клиентов ──────────────────────────────────────────────────────

// Получить страницу обращений клиентов с фильтрами.
export const fetchManagerRequests = (filters: RequestFilters = {}) =>
  request(
    () => api.get<RequestListResponse>(`${MANAGER}/order-requests`, { params: cleanParams(filters) }),
    "Обращения",
  );

// Получить одно обращение по id.
export async function fetchManagerRequest(id: number): Promise<ManagerOrderRequest> {
  // Запрос на детальное обращение.
  const response = await request(
    () => api.get<{ data: ManagerOrderRequest }>(`${MANAGER}/order-requests/${id}`),
    "Обращение",
  );
  // Достаём обращение из обёртки ответа.
  return response.data;
}

// Сменить статус обращения: взять/отпустить/решить/отклонить (с опциональным комментарием менеджера).
export const transitionManagerRequest = (
  id: number,
  action: "take" | "release" | "resolve" | "reject",
  managerComment?: string,
) => request(
  () => api.post<CommandResponse<ManagerOrderRequest>>(
    `${MANAGER}/order-requests/${id}/${action}`,
    // Тело отправляем только если комментарий передан (иначе пустой POST).
    managerComment === undefined ? undefined : { manager_comment: managerComment.trim() },
  ),
  "Обращение",
);

// ── Модерация ───────────────────────────────────────────────────────────────

// Получить страницу отзывов на товары с фильтрами.
export const fetchManagerReviews = (filters: ReviewFilters = {}) =>
  request(
    () => api.get<Paginated<ManagerReview>>(`${MANAGER}/reviews`, { params: cleanParams(filters) }),
    "Отзывы",
  );

// Получить один отзыв по id.
export async function fetchManagerReview(id: number): Promise<ManagerReview> {
  // Запрос на детальный отзыв.
  const response = await request(
    () => api.get<{ data: ManagerReview }>(`${MANAGER}/reviews/${id}`),
    "Отзыв",
  );
  // Достаём отзыв из обёртки.
  return response.data;
}

// Скрыть отзыв (с кодом причины и комментарием).
export const hideManagerReview = (id: number, reasonCode: string, comment: string) =>
  request(
    () => api.post<CommandResponse<ManagerReview>>(`${MANAGER}/reviews/${id}/hide`, {
      reason_code: reasonCode, // код причины скрытия
      comment: comment.trim(), // комментарий модератора
    }),
    "Отзыв",
  );

// Вернуть скрытый отзыв (восстановить публикацию).
export const restoreManagerReview = (id: number, comment: string) =>
  request(
    () => api.post<CommandResponse<ManagerReview>>(`${MANAGER}/reviews/${id}/restore`, { comment: comment.trim() }),
    "Отзыв",
  );

// Ответить на отзыв от имени компании (PUT).
export const replyManagerReview = (id: number, body: string) =>
  request(
    () => api.put<CommandResponse<ManagerReview>>(`${MANAGER}/reviews/${id}/reply`, { body: body.trim() }),
    "Отзыв",
  );

// Получить страницу обратной связи по доставке (оценки заказов).
export const fetchManagerFeedback = (filters: FeedbackFilters = {}) =>
  request(
    () => api.get<FeedbackListResponse>(`${MANAGER}/order-feedback`, { params: cleanParams(filters) }),
    "Оценки заказов",
  );

// Получить одну запись обратной связи по id.
export async function fetchManagerFeedbackItem(id: number): Promise<ManagerFeedback> {
  // Запрос на детальную обратную связь.
  const response = await request(
    () => api.get<{ data: ManagerFeedback }>(`${MANAGER}/order-feedback/${id}`),
    "Оценка заказа",
  );
  // Достаём запись из обёртки.
  return response.data;
}

// Скрыть запись обратной связи (с кодом причины и комментарием).
export const hideManagerFeedback = (id: number, reasonCode: string, comment: string) =>
  request(
    () => api.post<CommandResponse<ManagerFeedback>>(`${MANAGER}/order-feedback/${id}/hide`, {
      reason_code: reasonCode, // код причины скрытия
      comment: comment.trim(), // комментарий модератора
    }),
    "Оценка заказа",
  );

// Вернуть скрытую запись обратной связи.
export const restoreManagerFeedback = (id: number, comment: string) =>
  request(
    () => api.post<CommandResponse<ManagerFeedback>>(`${MANAGER}/order-feedback/${id}/restore`, { comment: comment.trim() }),
    "Оценка заказа",
  );
