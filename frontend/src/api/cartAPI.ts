import { api } from "./api";
import type { Cart, AddToCartRequest } from "../interfaces/cart";
import type { Order } from "../interfaces/order";
import type { CheckoutRequest } from "../interfaces/checkout";

const CART_PATH = "/api/cart";

export const cartApi = {
  /** Получить корзину текущего пользователя */
  async getCart(): Promise<Cart> {
    const { data } = await api.get<Cart>(CART_PATH);
    return data;
  },

  /** Добавить товар в корзину */
  async addItem(params: AddToCartRequest): Promise<Cart> {
    const { data } = await api.post<Cart>(`${CART_PATH}/add`, params);
    return data;
  },

  /** Обновить количество товара (quantity=0 удаляет) */
  async updateItemQuantity(itemId: number, quantity: number): Promise<Cart> {
    const { data } = await api.put<Cart>(`${CART_PATH}/update/${itemId}`, { quantity });
    return data;
  },

  /** Удалить товар из корзины */
  async removeItem(itemId: number): Promise<Cart> {
    const { data } = await api.delete<Cart>(`${CART_PATH}/remove/${itemId}`);
    return data;
  },

  /** Очистить корзину */
  async clearCart(): Promise<Cart> {
    const { data } = await api.delete<Cart>(`${CART_PATH}/clear`);
    return data;
  },

  /** Оформить заказ (самовывоз, без доставки) */
  async checkout(params: CheckoutRequest): Promise<Order> {
    const { data } = await api.post<Order>("/api/checkout", params);
    return data;
  },
};
