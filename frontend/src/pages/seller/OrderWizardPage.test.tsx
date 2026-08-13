import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import { describe, expect, it, vi } from "vitest";
import { SellerContext, type SellerContextValue } from "../../contexts/SellerContext";
import type { LocalOrder, LocalOrderItem, LocalProduct } from "../../seller/types";
import OrderWizardPage from "./OrderWizardPage";

vi.mock("framer-motion", async (importOriginal) => {
  const actual = await importOriginal<Record<string, unknown>>();
  return {
    ...actual,
    AnimatePresence: ({ children }: { children?: React.ReactNode }) => <>{children}</>,
    motion: new Proxy(
      {},
      {
        get: () => ({ children }: { children?: React.ReactNode }) => <>{children}</>,
      }
    ),
  };
});

function line(overrides: Partial<LocalOrderItem> = {}): LocalOrderItem {
  return {
    product_id: 1,
    quantity: 30,
    pricing_token: "tok",
    name: "Улун",
    unit_price: 250,
    price_unit_quantity: 100,
    stock_unit: "gram",
    sale_step: 10,
    ...overrides,
  };
}

function product(overrides: Partial<LocalProduct> = {}): LocalProduct {
  return {
    id: 1,
    name: "Улун",
    stock_unit: "gram",
    sale_step: 10,
    price_unit_quantity: 100,
    pricing: {
      unit_price: 250,
      issued_at: "2026-01-01T00:00:00.000Z",
      expires_at: "2026-02-01T00:00:00.000Z",
      automatic_promotions: [],
    },
    pricing_token: "tok",
    stock: {
      quantity: 500,
      reserved_online_quantity: 0,
      reserved_seller_quantity: 0,
      available_quantity: 500,
      shortage_quantity: 0,
    },
    warehouse_id: 1,
    image: null,
    ...overrides,
  };
}

function draft(overrides: Partial<LocalOrder> = {}): LocalOrder {
  const now = new Date().toISOString();
  return {
    client_order_id: "draft-1",
    server_id: null,
    revision: 1,
    acked_revision: 0,
    warehouse_id: 1,
    occurred_at: now,
    payment_method: null,
    items: [],
    status: "draft",
    server_status: null,
    server_status_name: null,
    order_number: null,
    actions: null,
    conflicts: [],
    last_error: null,
    last_error_fields: null,
    was_edited: false,
    customer_note: null,
    totals_preview: 0,
    created_at: now,
    updated_at: now,
    ...overrides,
  };
}

function sellerValue(overrides: Partial<SellerContextValue> = {}): SellerContextValue {
  return {
    session: null,
    workLocations: [],
    products: [product()],
    warehouseId: 1,
    ready: true,
    loading: false,
    error: null,
    online: true,
    snapshotExpired: false,
    init: vi.fn(),
    selectWarehouse: vi.fn(),
    refreshCatalog: vi.fn(),
    resetSession: vi.fn(),
    draft: null,
    startDraft: vi.fn(),
    openDraft: vi.fn(),
    closeDraft: vi.fn(),
    updateDraft: vi.fn(),
    addItem: vi.fn(),
    updateItemQuantity: vi.fn(),
    removeItem: vi.fn(),
    clearItems: vi.fn(),
    commitDraft: vi.fn(),
    orders: [],
    pendingCount: 0,
    syncing: new Set(),
    syncOrder: vi.fn(),
    syncAll: vi.fn(),
    enqueue: vi.fn(),
    completeDay: vi.fn(),
    deleteOrder: vi.fn(),
    reopen: vi.fn(),
    ...overrides,
  };
}

function renderWizard(value: SellerContextValue) {
  return render(
    <MemoryRouter initialEntries={["/seller/order/new"]}>
      <Routes>
        <Route
          path="/seller/order/new"
          element={
            <SellerContext.Provider value={value}>
              <OrderWizardPage />
            </SellerContext.Provider>
          }
        />
        <Route path="/seller/orders" element={<div>orders-page</div>} />
      </Routes>
    </MemoryRouter>
  );
}

describe("OrderWizardPage", () => {
  it("показывает спиннер при загрузке", () => {
    renderWizard(sellerValue({ loading: true, ready: false }));
    expect(screen.getByRole("progressbar")).toBeInTheDocument();
  });

  it("без точки предлагает вернуться на выбор", () => {
    renderWizard(sellerValue({ ready: false }));
    expect(screen.getByText("Выберите рабочую точку")).toBeInTheDocument();
    expect(screen.getByText("К выбору точки")).toBeInTheDocument();
  });

  it("создаёт черновик при входе в визард", () => {
    const startDraft = vi.fn().mockResolvedValue(draft());
    renderWizard(sellerValue({ draft: null, startDraft }));
    expect(startDraft).toHaveBeenCalled();
  });

  it("не даёт перейти дальше без позиций", () => {
    renderWizard(sellerValue({ draft: draft() }));
    const next = screen.getByRole("button", { name: "Далее" });
    expect(next).toBeDisabled();
  });

  it("добавляет товар из каталога", async () => {
    const addItem = vi.fn();
    renderWizard(sellerValue({ draft: draft(), addItem }));

    expect(screen.getByText("Улун")).toBeInTheDocument();
    await userEvent.click(screen.getByRole("button", { name: "+10 г" }));
    expect(addItem).toHaveBeenCalledWith(product());
  });

  it("проводит через все стадии до оформления", async () => {
    const commitDraft = vi.fn();
    const value = sellerValue({
      draft: draft({ items: [line()], payment_method: "cash", totals_preview: 75 }),
      commitDraft,
    });
    renderWizard(value);

    expect(screen.getByText("Улун")).toBeInTheDocument();

    await userEvent.click(screen.getByRole("button", { name: "Далее" }));
    expect(await screen.findByText("Очистить состав")).toBeInTheDocument();

    await userEvent.click(screen.getByRole("button", { name: "Далее" }));
    expect(await screen.findByText("Оплата")).toBeInTheDocument();
    expect(screen.getByText("Наличные")).toBeInTheDocument();
    expect(screen.getByText("Карта")).toBeInTheDocument();

    await userEvent.click(screen.getByRole("button", { name: "Далее" }));
    expect(await screen.findByText("Состав заказа")).toBeInTheDocument();

    await userEvent.click(screen.getByRole("button", { name: "Оформить продажу" }));
    expect(commitDraft).toHaveBeenCalledTimes(1);
    expect(await screen.findByText("orders-page")).toBeInTheDocument();
  });

  it("не позволяет добавить больше доступного остатка", () => {
    const full = product({
      stock: {
        quantity: 10,
        reserved_online_quantity: 0,
        reserved_seller_quantity: 0,
        available_quantity: 10,
        shortage_quantity: 0,
      },
    });
    renderWizard(
      sellerValue({
        products: [full],
        draft: draft({ items: [line({ quantity: 10 })] }),
      })
    );
    expect(screen.getByRole("button", { name: "+10 г" })).toBeDisabled();
  });

  it("выбирает способ оплаты", async () => {
    const updateDraft = vi.fn();
    renderWizard(
      sellerValue({
        draft: draft({ items: [line()], payment_method: null }),
        updateDraft,
      })
    );

    await userEvent.click(screen.getByRole("button", { name: "Далее" }));
    await userEvent.click(screen.getByRole("button", { name: "Далее" }));

    await userEvent.click(await screen.findByText("Наличные"));
    expect(updateDraft).toHaveBeenCalledWith({ payment_method: "cash" });
  });
});
