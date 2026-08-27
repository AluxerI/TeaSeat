import type { Page, Route } from "@playwright/test";

// Общие фикстуры делают браузерные сценарии воспроизводимыми без заполненной БД.
export const customerUser = {
  id: 7, name: "Анна Чайная", email: "anna@example.test", is_active: true,
  roles: ["user"], permissions: [], stats: { orders_count: 1, reviews_count: 0 },
};

const item = {
  id: 11, product_id: 5, quantity: 100,
  product: { id: 5, name: "Ассам", price: 320, image: null, weight_grams: 100, stock_unit: "gram", sale_step: 50, price_unit_quantity: 100, is_available: true },
  unit_price: 320, base_total: 320, stock_unit: "gram", sale_step: 50, price_unit_quantity: 100,
  promotion_discount_percent: 0, personal_discount_percent: 0, final_unit_price: 320,
  total_price: 320, saved_amount: 0,
};

export const gift = {
  id: 22, gift_id: 9, client_instance_id: "gift-instance", gift_version: 1,
  name: "Чайный вечер", description: null, quantity: 1, layout: {},
  prices: { markup_unit_amount: 50, markup_total_amount: 50, components_base_total: 600, components_discount_amount: 0, total_price: 650 }, items: [],
};

export const customerCart = {
  id: 1, order_number: "CART-1", status: "cart", status_name: "Корзина", items: [item], gifts: [gift],
  products_total: 920, gift_markup_total: 50, promotion_discount: 0, personal_discount: 0,
  cart_discount: 0, shipping_cost: 0, shipping_discount: 0, final_total: 970,
  shipping_address: null, delivery_method: null, selected_discount: null,
  is_supplier_order: false, can_checkout: true, created_at: "15.08.2026 10:00", updated_at: "15.08.2026 10:00",
};

export const customerOrder = {
  id: 91, status: "pending", sales_channel: "online", payment_method: "card", order_number: "TE-000091",
  is_partial: false, parent_order_id: null, order_type: "regular", order_type_name: "Обычный заказ", supplier_info: null,
  totals: { products_total: 970, promotion_discount: 0, personal_discount: 0, cart_discount: 0, shipping_cost: 0, shipping_discount: 0, final_total: 970 },
  delivery_info: { estimated_days: "1 день", has_multiple_warehouses: false, warehouse_count: 1 },
  delivery: {
    method: { id: 1, name: "Самовывоз", description: "Забрать в магазине", cost: 0, type: "pickup", provider_code: null, estimated_days: "Сегодня", requires_scheduling: false },
    scheduled_window: null,
    address: { id: 4, user_id: 7, city: "Москва", street: "Тверская, 1", postal_code: "101000", full_address: "101000, Москва, Тверская, 1" },
    warehouse: null, tracking_number: null,
  },
  items: [item], gifts: [gift], customer_notes: null,
  timestamps: { created_at: "15.08.2026 10:30", confirmed_at: null, paid_at: null, shipped_at: null, delivered_at: null, cancelled_at: null },
  status_info: { name: "Ожидает подтверждения", color: "yellow" },
};

export async function mockCustomerAuth(page: Page) {
  // Приложение проходит обычный путь AuthProvider, а не тестовый обход.
  await page.addInitScript((user) => {
    localStorage.setItem("auth_token", "customer-e2e-token");
    localStorage.setItem("auth_user_cache", JSON.stringify(user));
  }, customerUser);
  await page.route("**/api/user", (route) => route.fulfill({ json: { data: customerUser } }));
}

export async function mockCustomerCommerce(page: Page, onCheckout?: (body: any, route: Route) => Promise<void> | void) {
  await page.route("**/api/addresses**", async (route) => {
    if (route.request().method() === "POST") return route.fulfill({ json: { message: "Адрес сохранён", address: customerOrder.delivery.address } });
    return route.fulfill({ json: { addresses: [customerOrder.delivery.address] } });
  });
  await page.route("**/api/checkout/delivery-methods/**", (route) => route.fulfill({ json: { data: [customerOrder.delivery.method] } }));
  await page.route("**/api/cart**", async (route) => {
    const url = new URL(route.request().url());
    if (url.pathname.endsWith("/quote")) {
      const body = route.request().postDataJSON();
      const giftOnly = body.cart_item_ids.length === 0;
      return route.fulfill({ json: { data: {
        products_total: giftOnly ? 600 : 920, gift_markup_total: body.cart_gift_ids.length ? 50 : 0,
        promotion_discount: 0, personal_discount: 0, cart_discount: 0, shipping_cost: 0,
        final_total: giftOnly ? 650 : 970, cart_selection: { ...body, explicit: true },
      } } });
    }
    return route.fulfill({ json: { data: customerCart } });
  });
  await page.route("**/api/checkout", async (route) => {
    if (onCheckout) return onCheckout(route.request().postDataJSON(), route);
    return route.fulfill({ json: { data: customerOrder } });
  });
  await page.route("**/api/orders**", (route) => {
    const path = new URL(route.request().url()).pathname;
    return route.fulfill({ json: { data: /\/orders\/91$/.test(path) ? customerOrder : [customerOrder] } });
  });
}
