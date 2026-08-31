/** Адрес клиента */
export interface Address {
  id: number;
  user_id: number;
  street: string; // улица, дом, квартира
  city: string; // город
  postal_code: string; // почтовый индекс
  full_address: string; // "индекс, город, улица"
  created_at: string;
  updated_at: string;
}

/** Способ оплаты, который backend принимает в первой клиентской версии. */
export type PaymentMethod = "cash" | "card";

/** Выбор части корзины. Обычные строки и подарки передаются раздельно. */
export interface CartSelection {
  cart_item_ids: number[];
  cart_gift_ids: number[];
}

/** Способ доставки уже отфильтрован backend с учётом выбранного адреса. */
export interface DeliveryMethodOption {
  id: number;
  name: string;
  description: string | null;
  cost: number;
  type: string;
  provider_code: string | null;
  estimated_days: string;
  requires_scheduling: boolean;
  booking_horizon_days?: number | null;
}

/** Один доступный интервал. Вместимость фронтенд только показывает. */
export interface DeliverySlot {
  id: number;
  time_from: string;
  time_to: string;
  capacity: number;
  booked: number;
  remaining_capacity: number;
}

export interface DeliveryDate {
  date: string;
  weekday: number;
  slots: DeliverySlot[];
}

export interface DeliverySlotsResponse {
  delivery_method: Pick<DeliveryMethodOption, "id" | "name" | "type">;
  booking_horizon_days: number;
  dates: DeliveryDate[];
}

/** Серверный предварительный расчёт выбранной части корзины. */
export interface CartQuote {
  currency?: string;
  products_total: number;
  gift_markup_total?: number;
  promotion_discount: number;
  personal_discount: number;
  cart_discount: number;
  shipping_cost: number;
  shipping_discount?: number;
  final_total: number;
  cart_selection?: CartSelection & { explicit: boolean };
}

/** Тело checkout: клиент передаёт выбор, но никогда не передаёт рассчитанные суммы. */
export interface CheckoutRequest {
  shipping_address_id: number;
  delivery_method_id: number;
  payment_method: PaymentMethod;
  customer_notes?: string;
  scheduled_delivery_date?: string;
  delivery_time_slot_id?: number;
  cart_item_ids: number[];
  cart_gift_ids: number[];
}
