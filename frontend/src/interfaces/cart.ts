/** Упрощённая информация о товаре внутри корзины (от API) */
export interface CartProduct {
  id: number;
  name: string; // название товара
  price: number; // базовая цена (без скидок)
  image: URL | null; // URL изображения
  weight_grams: number; // вес в граммах
  is_available: boolean; // доступен ли товар к покупке
}

/** Одна позиция в корзине */
export interface CartItem {
  id: number; // ID записи order_products (CartItem)
  product_id: number; // ID товара
  quantity: number; // количество
  product: CartProduct; // данные товара
  unit_price: number; // цена за единицу до скидок
  promotion_discount_percent: number; // процент скидки по акции
  personal_discount_percent: number; // процент персональной скидки
  final_unit_price: number; // итоговая цена за единицу (с учётом скидок)
  total_price: number; // общая стоимость = final_unit_price * quantity
  saved_amount: number; // сколько сэкономили на этой позиции
}

/** Адрес доставки, привязанный к корзине (может отсутствовать) */
export interface CartDeliveryAddress {
  id: number;
  city: string;
  street: string;
  postal_code: string;
  full_address: string; // "индекс, город, улица"
}

/** Способ доставки, выбранный для корзины (может отсутствовать) */
// это потом
/**
export interface CartDeliveryMethod {
  id: number;
  name: string; // название способа доставки
  cost: number; // стоимость доставки
  estimated_days: string; // "1-3 дней", "от 2 дней" и т.д.
}
 */

/** Корзина текущего пользователя (по факту — Order со status === "cart") */
export interface Cart {
  id: number; // ID заказа (Order)
  order_number: string; // "TE-000001"
  status: string; // всегда "cart"
  status_name: string; // "Корзина"
  items: CartItem[]; // список позиций
  products_total: number; // сумма товаров без скидок
  promotion_discount: number; // скидка по акции (в деньгах)
  personal_discount: number; // персональная скидка (в деньгах)
  cart_discount: number; // дополнительная скидка на корзину
  shipping_cost: number; // стоимость доставки (0 пока не выбран способ)
  final_total: number; // итоговая сумма к оплате
  shipping_address: CartDeliveryAddress | null;
  delivery_method: null;
  is_supplier_order: boolean; // заказ через поставщика?
  can_checkout: boolean; // можно ли оформить (есть товары и сумма > 0)
  created_at: string;
  updated_at: string;
}

/** Тело запроса на добавление товара в корзину */
export interface AddToCartRequest {
  product_id: number;
  quantity: number;
  city?: string; // город для проверки наличия (опционально)
  is_supplier_order?: boolean; // заказ через поставщика
}
