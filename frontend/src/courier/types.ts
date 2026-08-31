/**
 * Типы — договор между backend и frontend о форме данных.
 * Interface сам ничего не загружает и не хранит: он лишь помогает TypeScript
 * заранее заметить пропущенное поле или неверный статус. CourierDelivery ниже
 * повторяет JSON одного заказа из backend-документации.
 *
 * PWA работает online-first: сервер остаётся источником истины, а `actions`
 * из ответа сервера решает, какие кнопки сейчас можно показать курьеру.
 */

export type DeliveryKind = "customer" | "transfer";

export type DeliveryStatus =
  | "ready_for_delivery"
  | "shipped"
  | "awaiting_receipt"
  | "delivered";

export type DeliveryCommand = "claim" | "release" | "start" | "deliver";
export type CourierListKey = "queue" | "mine" | "history";
export type RequestPhase = "idle" | "loading" | "success" | "error";

export interface CourierBrief {
  id: number;
  name: string;
  phone: string | null;
}

export interface CourierWarehouse {
  warehouse_id: number;
  name: string;
  city: string | null;
  address: string | null;
}

export interface CourierCustomer {
  id: number | null;
  name: string | null;
  email: string | null;
  phone: string | null;
}

export interface DeliveryAddress {
  id: number;
  city: string | null;
  street: string | null;
  postal_code: string | null;
  full_address: string;
}

export interface CourierDeliveryMethod {
  id: number;
  name: string;
  type: "courier" | "express";
  provider_code: string | null;
}

export interface ScheduledDeliveryWindow {
  date: string;
  time_from: string;
  time_to: string;
  slot_id: number;
  claim_opens_at: string | null;
}

export interface CourierPayment {
  method: "cash" | "card" | "online" | string;
  order_total: number;
  amount_to_collect: number;
}

export interface CourierDeliveryItem {
  product_id: number;
  product_name: string | null;
  quantity: number;
  stock_unit: string;
}

export interface CourierDeliveryActions {
  can_claim: boolean;
  can_release: boolean;
  can_start: boolean;
  can_deliver: boolean;
}

export interface CourierDeliveryTimestamps {
  ready_at: string | null;
  assigned_at: string | null;
  started_at: string | null;
  arrived_at: string | null;
  delivered_at: string | null;
}

export interface CourierDelivery {
  id: number;
  order_number: string | null;
  delivery_kind: DeliveryKind;
  status: DeliveryStatus;
  status_name: string;
  courier: CourierBrief | null;
  pickup: CourierWarehouse | null;
  transfer_destination: CourierWarehouse | null;
  customer: CourierCustomer | null;
  delivery_address: DeliveryAddress | null;
  delivery_method: CourierDeliveryMethod | null;
  scheduled_window: ScheduledDeliveryWindow | null;
  payment: CourierPayment | null;
  items: CourierDeliveryItem[];
  timestamps: CourierDeliveryTimestamps;
  actions: CourierDeliveryActions;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface CourierDeliveryListResponse {
  data: CourierDelivery[];
  meta: PaginationMeta;
}

export interface CourierDeliveryCommandResponse {
  message: string;
  data: CourierDelivery;
}

export interface CourierDeliveryFilters {
  status?: DeliveryStatus;
  warehouse_id?: number;
  delivery_kind?: DeliveryKind;
  mine?: boolean;
  per_page?: number;
  page?: number;
}

export interface CourierListState {
  ids: number[];
  phase: RequestPhase;
  error: import("./errors").StaffApiError | null;
  meta: PaginationMeta | null;
  lastFetchedAt: number | null;
}

/**
 * Это не «мутация React». Объект описывает состояние сетевой POST-команды:
 * например, кнопка заказа №42 сейчас ждёт ответ (`pending`) или получила ошибку.
 */
export interface DeliveryCommandState {
  type: DeliveryCommand;
  phase: "pending" | "error";
  error: import("./errors").StaffApiError | null;
}

export interface CourierNotice {
  severity: "success" | "info" | "warning" | "error";
  message: string;
}

/**
 * Общий state курьерской части. Он «нормализован»: полный заказ хранится один
 * раз в deliveries[id], а Queue/Mine/History содержат ссылки-id на него.
 * Так одна серверная версия заказа используется на всех экранах без копий.
 */
export interface CourierPwaState {
  deliveries: Record<number, CourierDelivery>;
  lists: Record<CourierListKey, CourierListState>;
  commands: Record<number, DeliveryCommandState | undefined>;
  online: boolean;
  lastSyncAt: number | null;
  notice: CourierNotice | null;
}
