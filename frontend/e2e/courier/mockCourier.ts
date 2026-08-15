import type { Page, Route } from "@playwright/test";

export const availableDelivery = {
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
  customer: { id: 7, name: "Анна", email: null, phone: "+79990000000" },
  delivery_address: {
    id: 5,
    city: "Санкт-Петербург",
    street: "Невский проспект, 17",
    postal_code: "190000",
    full_address: "190000, Санкт-Петербург, Невский проспект, 17",
  },
  delivery_method: { id: 1, name: "Курьером", type: "courier", provider_code: null },
  scheduled_window: {
    date: "2026-08-14",
    time_from: "16:00",
    time_to: "18:00",
    slot_id: 3,
    claim_opens_at: "2026-08-13T16:00:00+03:00",
  },
  payment: { method: "cash", order_total: 1540, amount_to_collect: 1540 },
  items: [{ product_id: 10, product_name: "Ассам", quantity: 100, stock_unit: "gram" }],
  timestamps: {
    ready_at: "2026-08-13T15:00:00+03:00",
    assigned_at: null,
    started_at: null,
    arrived_at: null,
    delivered_at: null,
  },
  actions: { can_claim: true, can_release: false, can_start: false, can_deliver: false },
};

const meta = (total: number) => ({
  current_page: 1,
  last_page: 1,
  per_page: 50,
  total,
});

export async function mockCourierAuth(page: Page) {
  const user = {
    id: 9,
    name: "Тестовый курьер",
    email: "courier@example.test",
    email_verified_at: null,
    phone: "+79991111111",
    phone_verified_at: null,
    provider: null,
    provider_id: null,
    is_active: true,
    roles: ["courier"],
    permissions: ["view assigned deliveries", "update assigned deliveries"],
    stats: { orders_count: 0, reviews_count: 0 },
    created_at: "2026-01-01T00:00:00Z",
    updated_at: "2026-01-01T00:00:00Z",
  };
  await page.addInitScript((cachedUser) => {
    localStorage.setItem("auth_token", "e2e-token");
    localStorage.setItem("auth_user_cache", JSON.stringify(cachedUser));
  }, user);
  await page.route("**/api/user", (route) => route.fulfill({ json: { data: user } }));
}

export async function fulfillList(route: Route, deliveries: unknown[]) {
  await route.fulfill({ json: { data: deliveries, meta: meta(deliveries.length) } });
}
