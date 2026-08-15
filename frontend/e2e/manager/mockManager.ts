import type { Page, Route } from "@playwright/test";

export const managerOrder = {
  id: 41,
  order_number: "TE-000041",
  status: "pending",
  status_name: "Ожидает подтверждения",
  sales_channel: "online",
  order_type: "regular",
  was_edited: false,
  customer: { id: 8, name: "Анна Чайная", email: "anna@example.test", phone: "+79990000000" },
  totals: { products_total: 1500, promotion_discount: 0, personal_discount: 0, cart_discount: 0, shipping_cost: 200, shipping_discount: 0, final_total: 1700 },
  payment: { method: "cash", paid_at: null, amount_to_collect: 1700 },
  delivery: {
    method: { id: 1, name: "Курьером", type: "courier", provider_code: null },
    scheduled_window: { date: "2026-08-19", time_from: "16:00", time_to: "18:00", slot_id: 12 },
    address: { id: 3, full_address: "Санкт-Петербург, Невский 17", city: "Санкт-Петербург", street: "Невский 17", postal_code: null },
    tracking_number: null,
    warehouse: { id: 7, name: "Флагман", city: "Санкт-Петербург", type: "store", is_active: true },
    destination_warehouse: null,
  },
  staff: { seller: null, picker: null, courier: null, seller_device: null },
  fulfillment_summary: { parts_count: 0, issues_count: 0, open_issues_count: 0 },
  actions: { can_add_internal_note: true, can_confirm: true, can_cancel: true, can_reschedule: true, can_modify_items: true },
  manager_access: { full_order_visible: true, read_only_contract: false, assigned_fulfillment_order_ids: [41], required_warehouse_ids: [7], all_locations_assigned: true },
  items: [{ id: 4, order_gift_id: null, product: { id: 10, name: "Ассам", stock_unit: "gram", sale_step: 10, price_unit_quantity: 100 }, quantity: 100, stock_unit: "gram", sale_step: 10, price_unit_quantity: 100, prices: { unit_price: 1500, base_total: 1500, promotion_discount_amount: 0, selected_discount_amount: 0, final_unit_price: 1500, total_price: 1500 } }],
  gifts: [],
  partial_orders: [],
  fulfillment_issues: [],
  inventory_movements: [],
  status_history: [],
  manager_adjustments: [],
  notes: { customer: "Позвонить перед доставкой", internal: null },
  timestamps: { created_at: "2026-08-15T08:00:00Z", updated_at: "2026-08-15T08:00:00Z" },
};

export const managerIssue = {
  id: 6,
  source_order_id: 55,
  product_id: 10,
  warehouse_id: 7,
  reason: "online_reservation_conflict",
  reason_message: "После физической продажи интернет-резервам не хватает 100 грамм товара.",
  shortage_quantity: 100,
  reserved_online_before: 300,
  reserved_seller_before: 100,
  status: "waiting",
  status_name: "Ожидает менеджера",
  manager_id: null,
  manager: null,
  product: { id: 10, name: "Ассам", stock_unit: "gram", sale_step: 10, price_unit_quantity: 100 },
  warehouse: { id: 7, name: "Флагман", city: "Санкт-Петербург", type: "store", is_active: true },
  source_order: { id: 55, order_number: "TE-SELLER-55", status: "manager_review", sales_channel: "seller", final_total: 900, seller: { id: 3, name: "Продавец" }, customer: null, items: [], latest_status_comment: null, occurred_at: null, synced_at: null, escalated_at: null },
  actions: { can_take: true, can_release: false, can_close: false, can_reopen: false },
  created_at: "2026-08-15T07:00:00Z",
  updated_at: "2026-08-15T07:00:00Z",
};

export const meta = (total: number) => ({ current_page: 1, last_page: 1, per_page: 20, total });

export async function mockManagerAuth(page: Page) {
  const user = {
    id: 2,
    name: "Тестовый менеджер",
    email: "manager@example.test",
    is_active: true,
    roles: ["manager"],
    permissions: ["view manager orders", "manage manager orders", "view fulfillment issues", "manage fulfillment issues", "view reviews", "moderate reviews", "manage orders", "assign couriers"],
    work_locations: [{ id: 7, name: "Флагман", city: "Санкт-Петербург", type: "store" }],
    stats: { orders_count: 0, reviews_count: 0 },
  };
  await page.addInitScript((cachedUser) => {
    localStorage.setItem("auth_token", "manager-e2e-token");
    localStorage.setItem("auth_user_cache", JSON.stringify(cachedUser));
  }, user);
  await page.route("**/api/user", (route) => route.fulfill({ json: { data: user } }));
}

export async function mockManagerCounters(page: Page) {
  await page.route("**/api/manager/order-requests**", (route) => route.fulfill({ json: { data: [], summary: { waiting: 0, in_review: 0, resolved: 0, rejected: 0, withdrawn: 0 }, meta: meta(0) } }));
}

export const fulfillIssueList = (route: Route, items: unknown[]) => route.fulfill({
  json: { data: items, summary: { waiting: items.length, in_review: 0, closed: 0 }, meta: meta(items.length) },
});
