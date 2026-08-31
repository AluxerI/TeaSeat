import { api } from "./api";
import { unwrapData } from "./unwrap";
import type { Cart, AddToCartRequest } from "../interfaces/cart";
import type { AddGiftToCartRequest } from "../interfaces/giftConstructor";
import type { Order } from "../interfaces/order";
import type {
  CartQuote,
  CartSelection,
  CheckoutRequest,
  DeliveryMethodOption,
  DeliverySlotsResponse,
} from "../interfaces/checkout";

const CART_PATH = "/api/cart";

/**
 * API покупательской корзины. Компоненты вызывают методы этого объекта и не
 * знают, заворачивает ли конкретный Laravel-контроллер результат в `data`.
 * Авторитетные цены всегда приходят с backend.
 */
export const cartApi = {
  async getCart(): Promise<Cart> {
    const response = await api.get<Cart | { data: Cart }>(CART_PATH);
    return unwrapData(response.data);
  },

  async addItem(params: AddToCartRequest): Promise<Cart> {
    const response = await api.post<Cart | { data: Cart }>(`${CART_PATH}/add`, params);
    return unwrapData(response.data);
  },

  async addGift(params: AddGiftToCartRequest): Promise<Cart> {
    const response = await api.post<Cart | { data: Cart }>(`${CART_PATH}/gifts`, params);
    return unwrapData(response.data);
  },

  async updateItemQuantity(itemId: number, quantity: number): Promise<Cart> {
    const response = await api.put<Cart | { data: Cart }>(`${CART_PATH}/update/${itemId}`, { quantity });
    return unwrapData(response.data);
  },

  async removeItem(itemId: number): Promise<Cart> {
    const response = await api.delete<Cart | { data: Cart }>(`${CART_PATH}/remove/${itemId}`);
    return unwrapData(response.data);
  },

  async clearCart(): Promise<Cart> {
    const response = await api.delete<Cart | { data: Cart }>(`${CART_PATH}/clear`);
    return unwrapData(response.data);
  },

  // Подарок обновляется целиком: его внутренние компоненты не являются
  // самостоятельными строками выбора на клиенте.
  async updateGiftQuantity(giftId: number, quantity: number): Promise<Cart> {
    const response = await api.put<Cart | { data: Cart }>(`${CART_PATH}/gifts/${giftId}`, { quantity });
    return unwrapData(response.data);
  },

  async removeGift(giftId: number): Promise<Cart> {
    const response = await api.delete<Cart | { data: Cart }>(`${CART_PATH}/gifts/${giftId}`);
    return unwrapData(response.data);
  },

  // Quote нужен и корзине, и checkout. Вторая страница добавляет адрес и способ
  // доставки, но выбранные строки остаются теми же.
  async quote(
    selection: CartSelection,
    delivery?: { addressId: number; methodId: number },
  ): Promise<CartQuote> {
    const payload = {
      ...selection,
      ...(delivery ? {
        shipping_address_id: delivery.addressId,
        delivery_method_id: delivery.methodId,
      } : {}),
    };
    const response = await api.post<CartQuote | { data: CartQuote }>(`${CART_PATH}/quote`, payload);
    return unwrapData(response.data);
  },

  async getDeliveryMethods(addressId: number): Promise<DeliveryMethodOption[]> {
    const response = await api.get<DeliveryMethodOption[] | { data: DeliveryMethodOption[] }>(
      `/api/checkout/delivery-methods/${addressId}`,
    );
    return unwrapData(response.data);
  },

  async getDeliverySlots(addressId: number, methodId: number): Promise<DeliverySlotsResponse> {
    const response = await api.get<DeliverySlotsResponse | { data: DeliverySlotsResponse }>(
      `/api/checkout/delivery-slots/${addressId}/${methodId}`,
    );
    return unwrapData(response.data);
  },

  // Idempotency-Key делает повтор после двойного клика или сетевого таймаута
  // безопасным: backend вернёт тот же заказ вместо создания второго.
  async checkout(params: CheckoutRequest, idempotencyKey: string): Promise<Order> {
    const response = await api.post<Order | { data: Order }>("/api/checkout", params, {
      headers: { "Idempotency-Key": idempotencyKey },
    });
    return unwrapData(response.data);
  },
};
