/** Типы PWA продавца.
 *
 *  Разделены на две группы:
 *  - `Api*` — то, что реально приходит с backend (см. backend/docs/seller-pwa.md);
 *  - `Local*` — то, что живёт в IndexedDB и переживает офлайн.
 *
 *  Локальные типы намеренно не совпадают с серверными: у локального заказа есть
 *  жизнь до первой синхронизации, когда серверного `id` ещё не существует.
 */

// ── Сервер ───────────────────────────────────────────────────────────────────

export type StockUnit = "piece" | "gram" | "milliliter" | string;

export interface ApiPricing {
  unit_price: number;
  issued_at: string;
  expires_at: string;
  automatic_promotions: unknown[];
}

export interface ApiStock {
  quantity: number;
  reserved_online_quantity: number;
  reserved_seller_quantity: number;
  available_quantity: number;
  shortage_quantity: number;
}

/** Товар из `GET /api/seller/bootstrap`. Картинки здесь нет — она
 *  домержовывается из публичного `/api/catalog`, см. `catalogImages.ts`. */
export interface ApiBootstrapProduct {
  id: number;
  name: string;
  stock_unit: StockUnit;
  sale_step: number;
  price_unit_quantity: number;
  pricing: ApiPricing;
  pricing_token: string;
  stock: ApiStock;
}

export interface ApiWarehouseBrief {
  id: number;
  name: string;
  city?: string | null;
  type: string;
}

export interface ApiBootstrap {
  server_time: string;
  price_snapshot_ttl_hours: number;
  warehouse: ApiWarehouseBrief;
  device: unknown;
  products: ApiBootstrapProduct[];
}

export type ServerOrderStatus =
  | "pending"
  | "seller_review"
  | "manager_review"
  | "completed"
  | "cancelled"
  | string;

export interface ApiSellerOrderActions {
  can_edit: boolean;
  can_cancel: boolean;
  can_complete: boolean;
  can_escalate: boolean;
}

export interface ApiSellerOrder {
  id: number;
  order_number: string;
  client_order_id: string;
  revision: number;
  sales_channel: string;
  status: ServerOrderStatus;
  status_name: string;
  payment_method: PaymentMethod;
  was_edited: boolean;
  actions: ApiSellerOrderActions;
  warehouse?: ApiWarehouseBrief;
  items?: unknown[];
  totals: {
    products_total: number;
    promotion_discount: number;
    final_total: number;
    currency: string;
  };
  timestamps: Record<string, string | null>;
}

/** Строка конфликта из `WarehouseService::completeSellerStockForOrder`. */
export interface ApiConflict {
  product_id: number;
  warehouse_id: number;
  reason: "online_reservation_conflict" | "physical_stock_discrepancy" | string;
  shortage_quantity: number;
  reserved_online_before: number;
  reserved_seller_before: number;
  requested_quantity: number;
  physical_quantity_before: number;
}

export type SyncResultKind =
  | "accepted"
  | "duplicate"
  | "completed"
  | "seller_review"
  | "manager_review"
  | "cancelled"
  | "rejected";

export interface ApiSyncResult {
  event_id: string;
  result: SyncResultKind;
  order?: ApiSellerOrder;
  conflicts?: ApiConflict[];
  message?: string;
  errors?: Record<string, string[]>;
}

export interface ApiSyncResponse {
  server_time: string;
  results: ApiSyncResult[];
}

// ── Локальное состояние ──────────────────────────────────────────────────────

export type PaymentMethod = "cash" | "card";

/** Клиентская стадия жизни заказа. Отдельно от `server_status`: заказ может
 *  быть `synced` локально и одновременно `seller_review` на сервере. */
export type LocalOrderStatus =
  | "draft"
  | "queued"
  | "syncing"
  | "synced"
  | "rejected";

export interface LocalOrderItem {
  product_id: number;
  /** В единицах `stock_unit`: штуки или граммы. Кратно `sale_step`. */
  quantity: number;
  pricing_token: string;
  // Снимок на момент добавления — чтобы показать сумму офлайн и не зависеть от
  // того, что каталог перезагрузили. Сервер всё равно пересчитает сам.
  name: string;
  unit_price: number;
  price_unit_quantity: number;
  stock_unit: StockUnit;
  sale_step: number;
}

export interface LocalOrder {
  client_order_id: string;
  server_id: number | null;
  /** Версия текущего локального содержимого. Всегда `acked_revision + 1`,
   *  пока правка не подтверждена сервером. */
  revision: number;
  /** Последняя версия, принятая сервером. 0 — заказ ещё не синхронизирован. */
  acked_revision: number;
  warehouse_id: number;
  occurred_at: string;
  payment_method: PaymentMethod | null;
  items: LocalOrderItem[];
  status: LocalOrderStatus;
  server_status: ServerOrderStatus | null;
  server_status_name: string | null;
  order_number: string | null;
  actions: ApiSellerOrderActions | null;
  conflicts: ApiConflict[];
  last_error: string | null;
  last_error_fields: Record<string, string[]> | null;
  was_edited: boolean;
  customer_note: string | null;
  /** Предварительная сумма по локальному снимку цен, только для показа. */
  totals_preview: number;
  created_at: string;
  updated_at: string;
}

export type OutboxAction = "upsert" | "cancel" | "complete" | "escalate";
export type OutboxState = "pending" | "inflight";

export interface OutboxEvent {
  event_id: string;
  client_order_id: string;
  action: OutboxAction;
  state: OutboxState;
  /** Версия, с которой событие уйдёт на сервер. */
  revision: number;
  created_at: string;
  attempts: number;
  last_error: string | null;
}

export interface LocalProduct extends ApiBootstrapProduct {
  warehouse_id: number;
  /** Домержено из публичного каталога, может отсутствовать в офлайне. */
  image: string | null;
}

/** Рабочая точка из `GET /api/user` → `work_locations`. */
export interface WorkLocation {
  id: number;
  name: string;
  city: string | null;
  type: string;
  is_online_fulfillment_enabled: boolean;
  is_delivery_hub: boolean;
}

export interface SellerSession {
  device_uuid: string;
  device_name: string;
  warehouse_id: number | null;
  warehouse_name: string | null;
  /** `server_time - Date.now()` на момент bootstrap, в миллисекундах. */
  clock_offset: number;
  /** ISO-время протухания снимка цен всего каталога (минимум по товарам). */
  snapshot_expires_at: string | null;
  last_bootstrap_at: string | null;
}
