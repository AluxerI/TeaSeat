import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { beforeEach, describe, expect, it, vi } from "vitest";
import type { Product } from "../../interfaces/catalog";

const mocks = vi.hoisted(() => ({
  addProduct: vi.fn(),
  close: vi.fn(),
}));

vi.mock("../../hooks/useAuth", () => ({ useAuth: () => ({ user: { id: 7 } }) }));
vi.mock("../../hooks/useCustomerCart", () => ({
  useCustomerCart: () => ({ addProduct: mocks.addProduct, pendingActionKey: null }),
}));

import ProductQuickViewDialog from "./ProductQuickViewDialog";

const product = {
  id: 5,
  name: "Ассам",
  brand: "TeaSeat",
  main_image: "/tea.jpg",
  description: "Крепкий индийский чай с насыщенным ароматом.",
  ingredients: "Чайный лист, сведения для полной страницы",
  final_price: 250,
  original_price: 300,
  discount_percent: 10,
  stock_unit: "gram",
  sale_step: 50,
  price_unit_quantity: 100,
  total_quantity: 160,
  is_available: true,
} as Product;

beforeEach(() => {
  mocks.addProduct.mockReset().mockResolvedValue({ id: 1, items: [], gifts: [] });
  mocks.close.mockReset();
});
describe("ProductQuickViewDialog", () => {
  it("показывает краткую информацию, но не превращается в полную карточку товара", () => {
    render(
      <MemoryRouter>
        <ProductQuickViewDialog product={product} open onClose={mocks.close} />
      </MemoryRouter>,
    );

    expect(screen.getByRole("heading", { name: "Ассам" })).toBeInTheDocument();
    expect(screen.getByText(product.description)).toBeInTheDocument();
    expect(screen.queryByText(product.ingredients)).not.toBeInTheDocument();
    expect(screen.getByText("В наличии: 150 г")).toBeInTheDocument();
  });

  it("добавляет допустимое количество и закрывается перед мини-корзиной", async () => {
    render(
      <MemoryRouter>
        <ProductQuickViewDialog product={product} open onClose={mocks.close} />
      </MemoryRouter>,
    );

    fireEvent.click(screen.getByRole("button", { name: "Увеличить количество Ассам" }));
    fireEvent.click(screen.getByRole("button", { name: "В корзину" }));

    await waitFor(() => expect(mocks.addProduct).toHaveBeenCalledWith({ product_id: 5, quantity: 150 }));
    expect(mocks.close).toHaveBeenCalledOnce();
  });
});
