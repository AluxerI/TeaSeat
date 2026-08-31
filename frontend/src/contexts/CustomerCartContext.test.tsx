import { act, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { Cart } from "../interfaces/cart";

const mocks = vi.hoisted(() => ({
  getCart: vi.fn(),
  addItem: vi.fn(),
  addGift: vi.fn(),
  updateItemQuantity: vi.fn(),
  removeItem: vi.fn(),
}));

vi.mock("../hooks/useAuth", () => ({ useAuth: () => ({ user: { id: 7 } }) }));
vi.mock("../api/cartAPI", () => ({ cartApi: mocks }));

import { CustomerCartProvider } from "./CustomerCartContext";
import { useCustomerCart } from "../hooks/useCustomerCart";

function cartSnapshot(id: number, productId?: number): Cart {
  return {
    id,
    items: productId ? [{ id: 11, product_id: productId }] : [],
    gifts: [],
    promotion_discount: 0,
    personal_discount: 0,
    cart_discount: 0,
    final_total: productId ? 300 : 0,
  } as unknown as Cart;
}

function Probe() {
  const cart = useCustomerCart();
  return (
    <>
      <span data-testid="cart-id">{cart.cart?.id ?? "empty"}</span>
      <span data-testid="drawer">{String(cart.miniCartOpen)}</span>
      <span data-testid="count">{cart.itemCount}</span>
      <button onClick={() => void cart.addProduct({ product_id: 5, quantity: 100 }).catch(() => undefined)}>
        Добавить
      </button>
      <button onClick={() => void cart.addGift({
        gift_id: 17,
        gift_version: 2,
        quantity: 1,
        client_instance_id: "11111111-1111-4111-8111-111111111111",
      }).catch(() => undefined)}>
        Добавить подарок
      </button>
      <button onClick={() => void cart.refreshCart().catch(() => undefined)}>Обновить</button>
    </>
  );
}

beforeEach(() => Object.values(mocks).forEach((mock) => mock.mockReset()));

describe("CustomerCartProvider", () => {
  it("принимает серверный снимок и открывает мини-корзину только после успеха", async () => {
    mocks.addItem.mockResolvedValue(cartSnapshot(9, 5));
    render(<CustomerCartProvider><Probe /></CustomerCartProvider>);

    fireEvent.click(screen.getByRole("button", { name: "Добавить" }));

    await waitFor(() => expect(screen.getByTestId("cart-id")).toHaveTextContent("9"));
    expect(screen.getByTestId("drawer")).toHaveTextContent("true");
    expect(screen.getByTestId("count")).toHaveTextContent("1");
    expect(mocks.addItem).toHaveBeenCalledWith({ product_id: 5, quantity: 100 });
  });

  it("не открывает Drawer при отклонённом запросе", async () => {
    mocks.addItem.mockRejectedValue({ response: { data: { message: "Недостаточно товара" } } });
    render(<CustomerCartProvider><Probe /></CustomerCartProvider>);

    fireEvent.click(screen.getByRole("button", { name: "Добавить" }));

    await waitFor(() => expect(mocks.addItem).toHaveBeenCalledOnce());
    expect(screen.getByTestId("drawer")).toHaveTextContent("false");
    expect(screen.getByTestId("cart-id")).toHaveTextContent("empty");
  });

  it("принимает подарок как одну серверную строку корзины", async () => {
    const nextCart = {
      ...cartSnapshot(12),
      gifts: [{ id: 31, gift_id: 17, quantity: 1 }],
    } as unknown as Cart;
    mocks.addGift.mockResolvedValue(nextCart);
    render(<CustomerCartProvider><Probe /></CustomerCartProvider>);

    fireEvent.click(screen.getByRole("button", { name: "Добавить подарок" }));

    await waitFor(() => expect(screen.getByTestId("cart-id")).toHaveTextContent("12"));
    expect(screen.getByTestId("drawer")).toHaveTextContent("true");
    expect(screen.getByTestId("count")).toHaveTextContent("1");
    expect(mocks.addGift).toHaveBeenCalledWith(expect.objectContaining({
      gift_id: 17,
      gift_version: 2,
      quantity: 1,
    }));
  });

  it("не позволяет старому GET затереть результат добавления", async () => {
    let resolveRefresh: ((value: Cart) => void) | undefined;
    mocks.getCart.mockReturnValue(new Promise<Cart>((resolve) => { resolveRefresh = resolve; }));
    mocks.addItem.mockResolvedValue(cartSnapshot(9, 5));
    render(<CustomerCartProvider><Probe /></CustomerCartProvider>);

    fireEvent.click(screen.getByRole("button", { name: "Обновить" }));
    fireEvent.click(screen.getByRole("button", { name: "Добавить" }));
    await waitFor(() => expect(screen.getByTestId("cart-id")).toHaveTextContent("9"));

    await act(async () => resolveRefresh?.(cartSnapshot(2)));
    expect(screen.getByTestId("cart-id")).toHaveTextContent("9");
  });
});
