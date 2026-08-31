import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { Cart } from "../interfaces/cart";

const mocks = vi.hoisted(() => ({
  user: { id: 1 }, getCart: vi.fn(), quote: vi.fn(), updateItemQuantity: vi.fn(),
  removeItem: vi.fn(), updateGiftQuantity: vi.fn(), removeGift: vi.fn(), replaceCart: vi.fn(),
}));
vi.mock("../api/cartAPI", () => ({ cartApi: mocks }));
vi.mock("../hooks/useAuth", () => ({ useAuth: () => ({ user: mocks.user, loading: false }) }));
vi.mock("../hooks/useCustomerCart", () => ({
  useCustomerCart: () => ({ replaceCart: mocks.replaceCart }),
}));
vi.mock("../ui/header/header", () => ({ default: () => <div data-testid="header" /> }));
vi.mock("../ui/footer/Footer", () => ({ default: () => <div data-testid="footer" /> }));
import CartPage from "./CartPage";

// Один обычный товар и один подарок ловят ошибку смешивания двух типов строк.
const cart = {
  id: 1, order_number: "CART-1", status: "cart", status_name: "Корзина",
  items: [{
    id: 11, product_id: 5, quantity: 100,
    product: { id: 5, name: "Ассам", price: 320, image: null, weight_grams: 100, stock_unit: "gram", sale_step: 50, price_unit_quantity: 100, is_available: true },
    unit_price: 320, base_total: 320, stock_unit: "gram", sale_step: 50, price_unit_quantity: 100,
    promotion_discount_percent: 0, personal_discount_percent: 0, final_unit_price: 320, total_price: 320, saved_amount: 0,
  }],
  gifts: [{ id: 22, gift_id: 9, client_instance_id: "gift-instance", gift_version: 1, name: "Чайный вечер", description: null, quantity: 1, layout: {}, prices: { markup_unit_amount: 50, markup_total_amount: 50, components_base_total: 600, components_discount_amount: 0, total_price: 650 }, items: [] }],
  products_total: 920, gift_markup_total: 50, promotion_discount: 0, personal_discount: 0,
  cart_discount: 0, shipping_cost: 0, shipping_discount: 0, final_total: 970,
  shipping_address: null, delivery_method: null, selected_discount: null,
  is_supplier_order: false, can_checkout: true, created_at: "", updated_at: "",
} satisfies Cart;

beforeEach(() => {
  Object.values(mocks).forEach((mock) => typeof mock === "function" && mock.mockReset());
  mocks.getCart.mockResolvedValue(cart);
  mocks.quote.mockImplementation(async (selection: { cart_item_ids: number[]; cart_gift_ids: number[] }) => ({
    products_total: selection.cart_item_ids.length ? 320 : 600,
    gift_markup_total: selection.cart_gift_ids.length ? 50 : 0,
    promotion_discount: 0, personal_discount: 0, cart_discount: 0, shipping_cost: 0,
    final_total: selection.cart_gift_ids.length && !selection.cart_item_ids.length ? 650 : 970,
    cart_selection: { ...selection, explicit: true },
  }));
  sessionStorage.clear();
});

describe("CartPage selective checkout", () => {
  it("передаёт подарок целиком и оставляет невыбранный товар", async () => {
    render(<MemoryRouter initialEntries={["/cart"]}><Routes><Route path="/cart" element={<CartPage />} /><Route path="/checkout" element={<div>CHECKOUT</div>} /></Routes></MemoryRouter>);
    await screen.findByText("Ассам");
    fireEvent.click(screen.getByLabelText(/Выбрать всё/));
    fireEvent.click(screen.getByLabelText("Выбрать подарок Чайный вечер"));
    await waitFor(() => expect(screen.getByRole("button", { name: "Оформить выбранное" })).toBeEnabled());
    fireEvent.click(screen.getByRole("button", { name: "Оформить выбранное" }));
    expect(await screen.findByText("CHECKOUT")).toBeInTheDocument();
    expect(JSON.parse(sessionStorage.getItem("customer_checkout_selection") ?? "{}")).toEqual({ cart_item_ids: [], cart_gift_ids: [22] });
  });
});
