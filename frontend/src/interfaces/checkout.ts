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

/** Способ оплаты: наличные или карта (онлайн не поддерживается) */
export type PaymentMethod = "cash" | "card";

/** Тело запроса оформления заказа (доставка пока не реализована — самовывоз) */
export interface CheckoutRequest {
  payment_method: PaymentMethod; // наличные / карта
  customer_notes?: string; // комментарий к заказу (до 500 символов)
}
