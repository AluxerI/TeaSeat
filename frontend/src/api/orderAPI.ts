import { api } from "./api";
import { unwrapData } from "./unwrap";
import type { Order } from "../interfaces/order";

const ORDERS_PATH = "/api/orders";

/** API заказов текущего покупателя. Пользователь определяется Sanctum-сессией. */
export const orderApi = {
  async getOrders(): Promise<Order[]> {
    const response = await api.get<Order[] | { data: Order[] }>(ORDERS_PATH);
    return unwrapData(response.data);
  },

  async getOrder(orderId: number): Promise<Order> {
    const response = await api.get<Order | { data: Order }>(`${ORDERS_PATH}/${orderId}`);
    return unwrapData(response.data);
  },

  // CancelController возвращает обновлённый ресурс заказа; отдельный message
  // интерфейсу не нужен.
  async cancelOrder(orderId: number): Promise<Order> {
    const response = await api.put<Order | { data: Order }>(`${ORDERS_PATH}/${orderId}/cancel`);
    return unwrapData(response.data);
  },
};
