import { api } from "./api";
import type { Order } from "../interfaces/order";

const ORDERS_PATH = "/orders";

export interface CancelResponse {
  message: string;
  data: Order;
}

export const orderApi = {
  /** Получить список заказов текущего пользователя */
  async getOrders(): Promise<Order[]> {
    const { data } = await api.get<Order[]>(ORDERS_PATH);
    return data;
  },

  /** Получить детали одного заказа */
  async getOrder(orderId: number): Promise<Order> {
    const { data } = await api.get<Order>(`${ORDERS_PATH}/${orderId}`);
    return data;
  },

  /** Отменить заказ */
  async cancelOrder(orderId: number): Promise<CancelResponse> {
    const { data } = await api.put<CancelResponse>(`${ORDERS_PATH}/${orderId}/cancel`);
    return data;
  },
};
