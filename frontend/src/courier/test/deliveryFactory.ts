import type { CourierDelivery } from "../types";

export function deliveryFactory(
  overrides: Partial<CourierDelivery> = {},
): CourierDelivery {
  return {
    id: 17,
    order_number: "TE-000017",
    delivery_kind: "customer",
    status: "ready_for_delivery",
    status_name: "Готов к передаче",
    courier: null,
    pickup: {
      warehouse_id: 2,
      name: "Флагман",
      city: "Санкт-Петербург",
      address: "Невский проспект, 1",
    },
    transfer_destination: null,
    customer: {
      id: 7,
      name: "Анна",
      email: "anna@example.test",
      phone: "+79990000000",
    },
    delivery_address: {
      id: 5,
      city: "Санкт-Петербург",
      street: "Невский проспект, 17",
      postal_code: "190000",
      full_address: "190000, Санкт-Петербург, Невский проспект, 17",
    },
    delivery_method: {
      id: 1,
      name: "Курьером",
      type: "courier",
      provider_code: null,
    },
    scheduled_window: {
      date: "2026-08-14",
      time_from: "16:00",
      time_to: "18:00",
      slot_id: 3,
      claim_opens_at: "2026-08-13T16:00:00+03:00",
    },
    payment: {
      method: "cash",
      order_total: 1540,
      amount_to_collect: 1540,
    },
    items: [
      { product_id: 10, product_name: "Ассам", quantity: 100, stock_unit: "gram" },
    ],
    timestamps: {
      ready_at: "2026-08-13T15:00:00+03:00",
      assigned_at: null,
      started_at: null,
      arrived_at: null,
      delivered_at: null,
    },
    actions: {
      can_claim: true,
      can_release: false,
      can_start: false,
      can_deliver: false,
    },
    ...overrides,
  };
}

export const paginationMeta = (overrides = {}) => ({
  current_page: 1,
  last_page: 1,
  per_page: 50,
  total: 1,
  ...overrides,
});
