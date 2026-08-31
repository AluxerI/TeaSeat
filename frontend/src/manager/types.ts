// Метаданные пагинации, которые приходят в ответе API.
export interface PaginationMeta {
  current_page: number; // номер текущей страницы (1-based)
  last_page: number; // номер последней страницы
  per_page: number; // количество элементов на странице
  total: number; // общее количество элементов во всей выборке
}

// Обобщённый контейнер пагинированного ответа: массив элементов + метаданные.
export interface Paginated<T> {
  data: T[]; // элементы текущей страницы
  meta: PaginationMeta; // метаданные пагинации
}

// Ответ на команды (confirm, cancel и т.п.): сообщение для пользователя + данные.
export interface CommandResponse<T> {
  message: string; // текст для показа в тосте/уведомлении
  data: T; // полезная нагрузка команды
}

// Краткое представление человека (пользователя/менеджера и т.д.) для списков.
export interface PersonBrief {
  id: number; // идентификатор пользователя
  name: string; // имя пользователя
  email?: string | null; // email (может отсутствовать)
  phone?: string | null; // телефон (может отсутствовать)
}

// Краткое представление склада для встраивания в другие сущности.
export interface WarehouseBrief {
  id: number; // идентификатор склада
  name: string; // название склада
  city?: string | null; // город склада
  type?: string; // тип склада (например, главный/магазин)
  is_active?: boolean; // активен ли склад
}

// Флаги: какие операции с заказом разрешены текущему менеджеру.
export interface ManagerOrderActions {
  can_add_internal_note: boolean; // можно добавить внутреннюю заметку
  can_confirm: boolean; // можно подтвердить заказ
  can_cancel: boolean; // можно отменить заказ
  can_reschedule: boolean; // можно перенести доставку
  can_modify_items: boolean; // можно менять состав заказа
}

// Права доступа менеджера к конкретному заказу.
export interface ManagerAccess {
  full_order_visible: boolean; // виден ли полный состав заказа
  read_only_contract: boolean; // заказ доступен только на чтение (контракт)
  assigned_fulfillment_order_ids: number[]; // id фулфилмент-заказов, назначенных менеджеру
  required_warehouse_ids: number[]; // склады, которые менеджер обязан уметь обработать
  all_locations_assigned: boolean; // назначены ли все точки (менеджер закрывает заказ целиком)
}

// Позиция заказа (товарная строка).
export interface ManagerOrderItem {
  id: number; // id позиции в заказе
  order_gift_id: number | null; // id подарочного набора, если позиция внутри набора
  product: {
    id: number; // id товара в каталоге
    name: string; // название товара
    image?: string | null; // ссылка на изображение
    stock_unit: string; // единица измерения (кг/шт/пачка)
    sale_step: number; // шаг продажи (минимальный шаг веса)
    price_unit_quantity: number; // количество товара в базовой единице цены
  } | null;
  quantity: number; // заказанное количество
  stock_unit: string; // единица измерения позиции
  sale_step: number; // шаг продажи позиции
  price_unit_quantity: number; // базовое количество для цены
  prices: {
    unit_price: number; // базовая цена за единицу
    base_total: number; // итог по базовой цене
    promotion_discount_amount: number; // скидка от акций
    selected_discount_amount: number; // выбранная персональная скидка
    final_unit_price: number; // итоговая цена за единицу после всех скидок
    total_price: number; // итоговая стоимость позиции
  };
}

// Подарочный набор в составе заказа (содержит вложенные позиции).
export interface ManagerOrderGift {
  id: number; // id записи набора в заказе
  gift_id: number; // id самого набора в каталоге
  gift_version: number; // версия конфигурации набора
  name: string; // название набора
  description: string | null; // описание набора
  quantity: number; // количество наборов
  prices: { total_price: number }; // цена набора
  items: ManagerOrderItem[]; // вложенные позиции набора
}

// Проблема комплектации, встроенная в карточку заказа.
export interface EmbeddedIssue {
  id: number; // id проблемы
  source_order_id: number; // id исходного заказа
  product: { id: number; name: string; stock_unit: string } | null; // товар, по которому проблема
  warehouse: WarehouseBrief | null; // склад, где обнаружена нехватка
  manager: PersonBrief | null; // менеджер, который взял проблему в работу
  reason: string; // код причины нехватки
  reason_message: string; // человекочитаемое описание причины
  shortage_quantity: number; // объём нехватки
  reserved_online_before: number; // резерв онлайн-заказов до инцидента
  reserved_seller_before: number; // резерв заказов продавцов до инцидента
  status: IssueStatus; // статус проблемы
  manager_accessible: boolean; // доступна ли проблема текущему менеджеру
  actions: Pick<IssueActions, "can_take" | "can_release" | "can_close">; // допустимые действия
  created_at: string | null; // дата создания
  updated_at: string | null; // дата последнего обновления
}

// Частичный (фулфилмент) заказ, на который разбивается клиентский заказ.
export interface PartialOrder {
  id: number; // id фулфилмент-заказа
  order_number: string; // номер заказа
  status: string; // внутренний код статуса
  status_name: string; // человекочитаемое название статуса
  warehouse: WarehouseBrief | null; // склад отправки
  destination_warehouse: WarehouseBrief | null; // склад назначения
  picker: PersonBrief | null; // сборщик
  courier: PersonBrief | null; // курьер
  final_total: number; // итоговая стоимость части заказа
  items: ManagerOrderItem[] | null; // позиции части заказа
  gifts: ManagerOrderGift[] | null; // наборы в части заказа
  internal_notes: string | null; // внутренние заметки менеджера
  status_history: StatusHistory[] | null; // история смены статусов
}

// Одна запись истории смены статуса заказа.
export interface StatusHistory {
  order_id?: number; // id заказа (для вложенных фулфилмент-заказов)
  from_status: string | null; // код предыдущего статуса
  from_status_name?: string | null; // название предыдущего статуса
  to_status: string; // код нового статуса
  to_status_name?: string; // название нового статуса
  changed_by: PersonBrief | null; // кто изменил статус
  notes: string | null; // примечание к изменению
  created_at: string | null; // момент изменения
}

// Движение остатков на складе, вызванное действиями менеджера.
export interface InventoryMovement {
  id: number; // id движения
  order_id: number; // связанный заказ
  product: { id: number; name: string } | null; // товар
  warehouse: WarehouseBrief | null; // склад
  actor: PersonBrief | null; // кто выполнил операцию
  type: string; // тип движения (например, manual/return/write-off)
  physical_delta: number; // изменение физического остатка
  reserved_online_delta: number; // изменение онлайн-резерва
  reserved_seller_delta: number; // изменение резерва продавцов
  reason: string | null; // причина движения
  created_at: string | null; // момент движения
}

// Запись об изменении заказа менеджером (аудит-лог).
export interface ManagerAdjustment {
  id: number; // id записи
  operation_id: string; // идемпотентность-ключ операции (защита от повторов)
  action: string; // тип действия (add/replace/remove/quantity)
  manager: PersonBrief | null; // менеджер, выполнивший действие
  fulfillment_issue_id: number | null; // связанная проблема комплектации
  reason: string; // причина изменения
  before: unknown; // состояние до изменения
  after: unknown; // состояние после изменения
  created_at: string | null; // момент изменения
}

// Полное представление заказа в кабинете менеджера.
export interface ManagerOrder {
  id: number; // id заказа
  order_number: string; // номер заказа (для клиента)
  status: string; // код текущего статуса
  status_name: string; // название текущего статуса
  sales_channel: "online" | "seller" | "internal"; // канал продаж
  order_type: "regular" | "supplier"; // тип заказа: обычный или поставщику
  was_edited: boolean; // редактировался ли заказ менеджером
  customer: PersonBrief | null; // покупатель
  totals: {
    products_total: number; // стоимость товаров
    promotion_discount: number; // скидка акций
    personal_discount: number; // персональная скидка
    cart_discount: number; // скидка корзины
    shipping_cost: number; // стоимость доставки
    shipping_discount: number; // скидка на доставку
    final_total: number; // итог к оплате
  };
  payment: { method: string | null; paid_at: string | null; amount_to_collect: number }; // способ оплаты, дата и сумма к получению
  delivery: {
    method: { id: number; name: string; type: string; provider_code: string | null } | null; // способ доставки
    scheduled_window: { date: string; time_from: string; time_to: string; slot_id: number | null } | null; // запланированное окно доставки
    address: { id: number; full_address: string; city: string; street: string; postal_code: string | null } | null; // адрес доставки
    tracking_number: string | null; // трек-номер
    warehouse: WarehouseBrief | null; // склад отправки
    destination_warehouse: WarehouseBrief | null; // склад назначения
  };
  staff: {
    seller: PersonBrief | null; // продавец
    picker: PersonBrief | null; // сборщик
    courier: PersonBrief | null; // курьер
    seller_device: { id: number; name: string; device_uuid: string; last_sync_at: string | null } | null; // устройство продавца
  };
  fulfillment_summary: { parts_count: number | null; issues_count: number; open_issues_count: number }; // сводка по частям и проблемам
  actions: ManagerOrderActions; // допустимые действия менеджера
  manager_access: ManagerAccess; // права доступа к заказу
  items?: ManagerOrderItem[]; // позиции заказа (в детальном режиме)
  gifts?: ManagerOrderGift[]; // подарочные наборы
  partial_orders?: PartialOrder[]; // фулфилмент-части заказа
  fulfillment_issues?: EmbeddedIssue[]; // проблемы комплектации заказа
  inventory_movements?: InventoryMovement[]; // движения остатков
  status_history?: StatusHistory[]; // история статусов
  manager_adjustments?: ManagerAdjustment[]; // изменения, сделанные менеджером
  notes?: { customer: string | null; internal: string | null }; // заметки: клиентская и внутренняя
  timestamps: Record<string, string | null>; // произвольные временные метки (created_at и т.п.)
}

// Параметры фильтрации списка заказов менеджера.
export interface ManagerOrderFilters {
  status?: string; // фильтр по статусу
  sales_channel?: "online" | "seller" | "internal"; // фильтр по каналу продаж
  warehouse_id?: number; // фильтр по складу
  has_issue?: boolean; // только заказы с проблемами
  date_from?: string; // начало периода (по дате заказа)
  date_to?: string; // конец периода
  search?: string; // текстовый поиск (номер заказа и т.д.)
  page?: number; // номер страницы
  per_page?: number; // размер страницы
}

// Статусы проблемы комплектации.
export type IssueStatus = "waiting" | "in_review" | "closed";
export interface IssueActions {
  can_take: boolean; // можно взять проблему в работу
  can_release: boolean; // можно отпустить проблему
  can_close: boolean; // можно закрыть проблему
  can_reopen: boolean; // можно переоткрыть проблему
}

// Полное представление проблемы комплектации.
export interface FulfillmentIssue {
  id: number; // id проблемы
  source_order_id: number; // id исходного заказа
  product_id: number; // id товара
  warehouse_id: number; // id склада
  reason: string; // код причины
  reason_message: string; // описание причины
  shortage_quantity: number; // объём нехватки
  reserved_online_before: number; // онлайн-резерв до инцидента
  reserved_seller_before: number; // резерв продавцов до инцидента
  status: IssueStatus; // статус
  status_name: string; // название статуса
  manager_id: number | null; // id менеджера, работающего с проблемой
  manager: PersonBrief | null; // менеджер
  product?: { id: number; name: string; stock_unit: string; sale_step: number; price_unit_quantity: number }; // товар
  warehouse?: WarehouseBrief; // склад
  source_order?: {
    id: number; // id исходного заказа
    order_number: string; // номер заказа
    status: string; // статус заказа
    sales_channel: string; // канал продаж
    final_total: number; // итоговая стоимость
    seller: PersonBrief | null; // продавец
    customer: PersonBrief | null; // покупатель
    items: Array<{ product_id: number; product_name: string; quantity: number; stock_unit: string; total_price: number }>; // позиции заказа
    latest_status_comment: string | null; // последний комментарий к статусу
    occurred_at: string | null; // когда возникла проблема
    synced_at: string | null; // когда проблема синхронизирована
    escalated_at: string | null; // когда проблема эскалирована
  };
  actions: IssueActions; // допустимые действия
  created_at: string | null; // дата создания
  updated_at: string | null; // дата обновления
}

// Параметры фильтрации списка проблем.
export interface IssueFilters {
  status?: IssueStatus | "all"; // фильтр по статусу
  warehouse_id?: number; // фильтр по складу
  product_id?: number; // фильтр по товару
  reason?: string; // фильтр по причине
  mine?: boolean; // только мои проблемы
  page?: number; // номер страницы
  per_page?: number; // размер страницы
}

// Ответ списка проблем: страница + сводка по количеству статусов.
export interface IssueListResponse extends Paginated<FulfillmentIssue> {
  summary: Record<IssueStatus, number>; // счётчики по каждому статусу
}

// Заказ, который может быть затронут переносом резерва проблемы.
export interface AffectedOrder {
  fulfillment_order_id: number; // id фулфилмент-заказа
  customer_order_id: number; // id клиентского заказа
  customer_order_number: string; // номер клиентского заказа
  reserved_quantity: number; // зарезервированное количество
  stock_unit: string; // единица измерения
  line_total: number; // стоимость строки
  fulfillment_status: string; // статус фулфилмент-заказа
  customer_order_status: string; // статус клиентского заказа
  customer: PersonBrief; // покупатель
  delivery: { method: { id: number; name: string } | null; address: { full_address: string } | null }; // способ и адрес доставки
  timestamps: { created_at: string | null; reserved_at: string | null }; // временные метки заказа и резерва
}

// Ответ со списком затронутых заказов: страница + сама проблема + сводка.
export interface AffectedOrdersResponse extends Paginated<AffectedOrder> {
  issue: FulfillmentIssue; // проблема, для которой ищутся затронутые заказы
  summary: { candidate_orders_count: number; reserved_quantity_total: number; shortage_quantity: number }; // сводка по резервам
}

// Статусы заявки клиента менеджеру.
export type RequestStatus = "waiting" | "in_review" | "resolved" | "rejected" | "withdrawn";
// Типы заявок клиента.
export type RequestType = "change_delivery" | "cancel_order" | "order_problem" | "other";

// Заявка клиента на изменение заказа (перенос, отмена, проблема).
export interface ManagerOrderRequest {
  id: number; // id заявки
  order_id: number; // id заказа
  type: RequestType; // тип заявки
  message: string | null; // текст обращения клиента
  status: RequestStatus; // статус заявки
  manager_comment: string | null; // комментарий менеджера
  manager: PersonBrief | null; // менеджер, взявший заявку
  customer?: PersonBrief; // клиент
  order?: {
    id: number; // id заказа
    order_number: string; // номер заказа
    status: string; // статус заказа
    warehouse: WarehouseBrief | null; // склад отправки
    destination_warehouse: WarehouseBrief | null; // склад назначения
    created_at: string | null; // дата создания заказа
  };
  actions: { can_take: boolean; can_release: boolean; can_resolve: boolean; can_reject: boolean }; // допустимые действия
  created_at: string | null; // дата создания заявки
  updated_at: string | null; // дата обновления заявки
}

// Параметры фильтрации заявок.
export interface RequestFilters {
  status?: RequestStatus | "all"; // фильтр по статусу
  type?: RequestType; // фильтр по типу
  order_id?: number; // фильтр по заказу
  mine?: boolean; // только мои заявки
  page?: number; // номер страницы
  per_page?: number; // размер страницы
}

// Ответ списка заявок: страница + сводка по статусам.
export interface RequestListResponse extends Paginated<ManagerOrderRequest> {
  summary: Record<RequestStatus, number>; // счётчики по статусам
}

// Статусы модерации отзывов/обратной связи.
export type ModerationStatus = "published" | "hidden";
// Запись в журнале модерации (кто и что сделал с отзывом).
export interface ModerationLog {
  id: number; // id записи
  action: string; // действие (hide/restore/reply и т.д.)
  reason_code: string | null; // код причины
  comment: string | null; // комментарий модератора
  moderator: PersonBrief; // кто совершил действие
  created_at: string | null; // момент действия
}

// Отзыв на товар в кабинете модерации.
export interface ManagerReview {
  id: number; // id отзыва
  product: { id: number; name: string }; // товар, на который оставлен отзыв
  author: PersonBrief; // автор отзыва
  customer: PersonBrief; // покупатель
  rating: number; // оценка (1-5)
  comment: string | null; // текст отзыва
  status: ModerationStatus; // статус модерации
  verified_purchase: boolean; // подтверждена ли покупка
  company_reply: { author_name: string; body: string; created_at: string | null; edited_at: string | null } | null; // ответ компании
  source_order: { id: number; status: string } | null; // заказ, к которому привязан отзыв
  latest_moderation: ModerationLog | null; // последнее событие модерации
  moderation_history?: ModerationLog[]; // вся история модерации
  actions: { can_hide: boolean; can_restore: boolean; can_reply: boolean; can_edit_customer_text: false }; // допустимые действия
  created_at: string | null; // дата отзыва
}

// Обратная связь по доставке заказа.
export interface ManagerFeedback {
  id: number; // id записи
  order_id: number; // id заказа
  ratings: { delivery: number | null; packing: number | null; service: number | null }; // оценки по категориям
  comment: string | null; // текст обратной связи
  status: ModerationStatus; // статус модерации
  customer: PersonBrief; // клиент
  order: { id: number; status: string; created_at: string | null }; // заказ
  latest_moderation: ModerationLog | null; // последнее событие модерации
  moderation_history?: ModerationLog[]; // история модерации
  actions: { can_hide: boolean; can_restore: boolean; can_edit_customer_text: false }; // допустимые действия
  created_at: string | null; // дата создания
}

// Параметры фильтрации отзывов на товары.
export interface ReviewFilters {
  status?: ModerationStatus | "all"; // фильтр по статусу
  rating?: number; // фильтр по оценке
  product_id?: number; // фильтр по товару
  search?: string; // текстовый поиск
  page?: number; // номер страницы
  per_page?: number; // размер страницы
}

// Параметры фильтрации обратной связи по доставке.
export interface FeedbackFilters {
  status?: ModerationStatus | "all"; // фильтр по статусу
  search?: string; // текстовый поиск
  page?: number; // номер страницы
  per_page?: number; // размер страницы
}

// Ответ списка обратной связи: страница + сводка средних оценок.
export interface FeedbackListResponse extends Paginated<ManagerFeedback> {
  summary: Record<"delivery" | "packing" | "service", { average: number | null; count: number }>; // средние оценки и количество
}

// Общая часть тела команды изменения состава заказа.
export interface ItemCommandBase {
  operation_id: string; // уникальный id операции (защита от дублей)
  reason: string; // причина изменения
  fulfillment_issue_id?: number; // связь с проблемой комплектации
}

// Уведомление для пользователя (тост/баннер).
export interface Notice {
  severity: "success" | "info" | "warning" | "error"; // уровень важности
  message: string; // текст уведомления
}
