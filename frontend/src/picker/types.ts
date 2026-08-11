/** Типы PWA сборщика.
 *
 *  Здесь описана «форма данных», которую присылает бэкенд (то же, что
 *  `PickerOrderResource` в backend/docs/picker-courier.md). Сборщик — онлайн-роль:
 *  все данные тянем с сервера напрямую, офлайн-очереди команд (как у продавца)
 *  у нас нет. Офлайн в PWA ограничен только статическим precache — страницы
 *  открываются, а данные уже нет.
 */

// Тип задания: «сборка» (обычный заказ) или «объединение» (сборка из нескольких заказов)
export type PickerJobType = "source" | "consolidation";

// Статусы, через которые проходит заказ сборщика.
// Обычная цепочка: confirmed → processing → ready_for_delivery → shipped → delivered.
// `| string` добавлен на случай новых статусов с бэка, чтобы тип не ломался.
export type PickerOrderStatus =
  | "confirmed"
  | "processing"
  | "ready_for_delivery"
  | "shipped"
  | "delivered"
  | "awaiting_receipt"
  | string;

// Короткая карточка склада (где собираем / куда везём)
export interface PickerWarehouseBrief {
  id: number;
  name: string;
  city?: string | null;
  location?: string | null;
}

// Короткая карточка пользователя (например, кто сейчас собирает заказ)
export interface PickerUserBrief {
  id: number;
  name: string;
}

// Позиция в составе заказа. Одна строка = один товар в заказе.
export interface PickerOrderItem {
  order_product_id: number; // id позиции (нужен как ключ в списках React)
  product_id: number; // id товара (нужен для формы «Недостача»)
  product_name: string | null;
  order_gift_id: number | null; // если товар входит в подарочный набор — id набора
  gift_name: string | null; // название набора, в который входит товар
  gift_item_client_id: string | null; // технический id строки набора из конструктора
  gift_item_quantity: number | null; // сколько штук набора
  quantity: number; // сколько нужно собрать
  stock_unit: string; // единица: gram / milliliter / piece
  sale_step: number; // шаг отгрузки (например, можно отгружать только по 10 г)
}

// Один товар внутри подарочного набора (рецептура набора)
export interface PickerGiftComponent {
  order_product_id: number;
  product_id: number;
  product_name: string | null;
  quantity: number; // сколько этого товара кладём в набор
  stock_unit: string;
  gift_item_client_id: string | null;
}

// Подарочный набор целиком
export interface PickerGift {
  order_gift_id: number;
  name: string | null;
  quantity: number; // сколько таких наборов заказано
  /** Снимок раскладки из конструктора: куда на подносе что стоит.
   *  Каждый элемент — клетка: координаты (position_x, position_y),
   *  повёрнут ли товар (is_rotated). По этим данным рисуется «схема раскладки». */
  layout: Array<{
    product_size_id: number;
    position_x: number;
    position_y: number;
    is_rotated?: boolean;
    sort_order?: number;
  }> | null;
  components: PickerGiftComponent[]; // из чего набор состоит
}

// Какие кнопки разрешены сейчас. Решает СЕРВЕР — фронт просто рисует то,
// что тут true. Благодаря этому не надо гадать, можно ли «взять» или «завершить».
export interface PickerOrderActions {
  can_take: boolean; // взять в сборку
  can_release: boolean; // вернуть в очередь
  can_complete: boolean; // завершить сборку
  can_escalate: boolean; // передать менеджеру
  can_report_shortage: boolean; // сообщить о недостаче
  can_receive: boolean; // подтвердить получение трансфера
}

// Ключевые моменты времени жизни заказа
export interface PickerOrderTimestamps {
  created_at: string | null;
  picking_started_at: string | null; // когда сборщик взял заказ
  ready_for_delivery_at: string | null; // когда собрали и можно везти
  courier_arrived_at: string | null;
  received_at: string | null; // когда доехавший заказ приняли на складе
}

// Сам заказ сборщика — главный объект приложения
export interface PickerOrder {
  id: number;
  order_number: string; // внутренний номер задания (например P-0007)
  customer_order_id: number;
  customer_order_number: string; // номер клиентского заказа (для сверки)
  job_type: PickerJobType;
  status: PickerOrderStatus;
  status_name: string; // человекочитаемый статус для чипа
  warehouse: PickerWarehouseBrief | null; // склад, где собираем
  destination_warehouse: PickerWarehouseBrief | null; // куда везём (для трансферов)
  picker: PickerUserBrief | null; // кто сейчас собирает
  items: PickerOrderItem[]; // состав заказа
  gifts: PickerGift[]; // подарочные наборы
  customer_notes: string | null; // комментарий клиента (показываем сборщику)
  timestamps: PickerOrderTimestamps;
  actions: PickerOrderActions;
}

// Пагинация очереди
export interface PickerPaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

// Ответ списком: сам список + метаданные пагинации
export interface PickerListResponse {
  data: PickerOrder[];
  meta: PickerPaginationMeta;
}

// Инцидент при недостаче (создаётся через «Недостача» на странице заказа)
export interface FulfillmentIssue {
  id: number;
  source_order_id: number;
  product_id: number;
  warehouse_id: number;
  reason: string;
  reason_message: string;
  shortage_quantity: number; // сколько не хватило
  status: string;
  created_at: string | null;
  updated_at: string | null;
}

// Фильтры очереди: по какому складу, чьи заказы (mine), какой тип работы и т.д.
export interface PickerQueueFilters {
  status?: string;
  warehouse_id?: number;
  job_type?: PickerJobType;
  mine?: boolean; // true — только заказы текущего сборщика
  per_page?: number;
}
