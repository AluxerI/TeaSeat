import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import { describe, expect, it, vi } from "vitest";
import type { PickerOrder } from "../../picker/types";
import PickerOrderPage from "./PickerOrderPage";

const mocks = vi.hoisted(() => ({
  usePicker: vi.fn(),
}));

vi.mock("../../picker/PickerContext", () => ({
  usePicker: mocks.usePicker,
}));

function order(overrides: Partial<PickerOrder> = {}): PickerOrder {
  return {
    id: 7,
    order_number: "P-0007",
    customer_order_id: 11,
    customer_order_number: "A-0011",
    job_type: "source",
    status: "processing",
    status_name: "В сборке",
    warehouse: { id: 1, name: "Флагман" },
    destination_warehouse: { id: 2, name: "Островок" },
    picker: { id: 3, name: "Иван" },
    items: [
      {
        order_product_id: 101,
        product_id: 3,
        product_name: "Пуэр",
        order_gift_id: null,
        gift_name: null,
        gift_item_client_id: null,
        gift_item_quantity: null,
        quantity: 200,
        stock_unit: "gram",
        sale_step: 50,
      },
    ],
    gifts: [
      {
        order_gift_id: 9,
        name: "Подарок №1",
        quantity: 1,
        layout: [
          { product_size_id: 1, position_x: 0, position_y: 0, is_rotated: false, sort_order: 1 },
          { product_size_id: 2, position_x: 1, position_y: 0, is_rotated: true, sort_order: 2 },
        ],
        components: [
          {
            order_product_id: 201,
            product_id: 3,
            product_name: "Пуэр",
            quantity: 200,
            stock_unit: "gram",
            gift_item_client_id: null,
          },
        ],
      },
    ],
    customer_notes: "Позвонить перед доставкой",
    timestamps: {
      created_at: "2026-01-01T10:00:00.000Z",
      picking_started_at: "2026-01-01T10:05:00.000Z",
      ready_for_delivery_at: null,
      courier_arrived_at: null,
      received_at: null,
    },
    actions: {
      can_take: true,
      can_release: true,
      can_complete: true,
      can_escalate: true,
      can_report_shortage: true,
      can_receive: false,
    },
    ...overrides,
  };
}

function pickerValue(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    loadOrder: vi.fn().mockResolvedValue(order()),
    take: vi.fn().mockResolvedValue(order()),
    release: vi.fn().mockResolvedValue(order()),
    complete: vi.fn().mockResolvedValue(order()),
    escalate: vi.fn().mockResolvedValue(order()),
    reportShortage: vi.fn().mockResolvedValue({ order: order(), fulfillment_issue: {} }),
    receive: vi.fn().mockResolvedValue(order()),
    online: true,
    ...overrides,
  };
}

function renderOrder(value: ReturnType<typeof pickerValue>) {
  mocks.usePicker.mockReturnValue(value);
  return render(
    <MemoryRouter initialEntries={["/picker/orders/7"]}>
      <Routes>
        <Route path="/picker/orders/:orderId" element={<PickerOrderPage />} />
        <Route path="/picker" element={<div>queue-page</div>} />
      </Routes>
    </MemoryRouter>
  );
}

describe("PickerOrderPage", () => {
  it("грузит заказ по id из маршрута", async () => {
    const loadOrder = vi.fn().mockResolvedValue(order());
    renderOrder(pickerValue({ loadOrder }));

    expect(await screen.findByText("P-0007")).toBeInTheDocument();
    expect(loadOrder).toHaveBeenCalledWith(7);
  });

  it("рисует состав, рецептуру и схему раскладки", async () => {
    renderOrder(pickerValue());

    expect(await screen.findAllByText("Пуэр")).toHaveLength(2);
    expect(screen.getByText("Подарок №1")).toBeInTheDocument();
    expect(screen.getByText("Схема раскладки")).toBeInTheDocument();
    expect(screen.getByText("↻")).toBeInTheDocument();
    expect(screen.getByText("Комментарий клиента")).toBeInTheDocument();
    expect(screen.getByText("Позвонить перед доставкой")).toBeInTheDocument();
  });

  it("кнопка «Взять в сборку» вызывает take", async () => {
    const take = vi.fn().mockResolvedValue(order());
    renderOrder(pickerValue({ take }));

    await screen.findByText("P-0007");
    await userEvent.click(screen.getByRole("button", { name: "Взять в сборку" }));
    await waitFor(() => expect(take).toHaveBeenCalledWith(7));
  });

  it("кнопка «Завершить сборку» вызывает complete", async () => {
    const loadOrder = vi.fn().mockResolvedValue(order());
    const complete = vi.fn().mockResolvedValue(
      order({ status: "ready_for_delivery", status_name: "Готов к доставке" }),
    );
    renderOrder(pickerValue({ complete, loadOrder }));

    await screen.findByText("P-0007");
    await userEvent.click(screen.getByRole("button", { name: "Завершить сборку" }));
    await waitFor(() => expect(complete).toHaveBeenCalledWith(7));
    expect(await screen.findByText("Готов к доставке")).toBeInTheDocument();
    // После успешного POST используем его data, а не делаем GET уже закрытого задания.
    expect(loadOrder).toHaveBeenCalledTimes(1);
  });

  it("диалог недостачи шлёт reportShortage", async () => {
    const reportShortage = vi.fn().mockResolvedValue({
      order: order(),
      fulfillment_issue: {},
    });
    const value = pickerValue({ reportShortage });
    value.loadOrder = vi.fn().mockResolvedValue(order());
    renderOrder(value);

    await screen.findByText("P-0007");
    await userEvent.click(screen.getByRole("button", { name: "Недостача" }));

    expect(await screen.findByText("Сообщить о недостаче")).toBeInTheDocument();
    await userEvent.click(screen.getByRole("button", { name: "Передать менеджеру" }));

    await waitFor(() =>
      expect(reportShortage).toHaveBeenCalledWith(
        7,
        expect.objectContaining({ product_id: 3, shortage_quantity: 1 })
      )
    );
  });

  it("диалог эскалации требует комментарий", async () => {
    const escalate = vi.fn().mockResolvedValue(order());
    renderOrder(pickerValue({ escalate }));

    await screen.findByText("P-0007");
    await userEvent.click(screen.getByRole("button", { name: "Передать менеджеру" }));

    const submit = await screen.findByRole("button", { name: "Передать" });
    expect(submit).toBeDisabled();

    await userEvent.type(screen.getByLabelText("Причина"), "Сломался весы");
    expect(submit).toBeEnabled();

    await userEvent.click(submit);
    await waitFor(() => expect(escalate).toHaveBeenCalledWith(7, "Сломался весы"));
  });

  it("кнопка назад ведёт в очередь", async () => {
    renderOrder(pickerValue());

    await screen.findByText("P-0007");
    await userEvent.click(screen.getByRole("button", { name: "Очередь" }));
    expect(await screen.findByText("queue-page")).toBeInTheDocument();
  });

  it("offline оставляет детали доступными для чтения, но блокирует команды", async () => {
    renderOrder(pickerValue({ online: false }));

    expect(await screen.findByText("P-0007")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Взять в сборку" })).toBeDisabled();
    expect(screen.getByRole("button", { name: "Завершить сборку" })).toBeDisabled();
    expect(screen.getByRole("button", { name: "Недостача" })).toBeDisabled();
  });
});
