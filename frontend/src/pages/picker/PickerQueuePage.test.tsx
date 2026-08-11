import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import { describe, expect, it, vi } from "vitest";
import type { PickerOrder } from "../../picker/types";
import PickerQueuePage from "./PickerQueuePage";

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
    status: "confirmed",
    status_name: "Подтверждён",
    warehouse: { id: 1, name: "Флагман" },
    destination_warehouse: null,
    picker: null,
    items: [],
    gifts: [],
    customer_notes: null,
    timestamps: {
      created_at: "2026-01-01T10:00:00.000Z",
      picking_started_at: null,
      ready_for_delivery_at: null,
      courier_arrived_at: null,
      received_at: null,
    },
    actions: {
      can_take: true,
      can_release: false,
      can_complete: false,
      can_escalate: false,
      can_report_shortage: false,
      can_receive: false,
    },
    ...overrides,
  };
}

function pickerValue(overrides: Partial<Record<"queue" | "myOrders" | "take" | "loading" | "error" | "refresh", unknown>> = {}) {
  return {
    queue: [],
    myOrders: [],
    take: vi.fn(),
    loading: false,
    error: null,
    refresh: vi.fn(),
    ...overrides,
  };
}

function renderQueue(
  mode: "queue" | "mine",
  value: ReturnType<typeof pickerValue>
) {
  mocks.usePicker.mockReturnValue(value);
  return render(
    <MemoryRouter initialEntries={["/picker"]}>
      <Routes>
        <Route path="/picker" element={<PickerQueuePage mode={mode} />} />
        <Route path="/picker/orders/:orderId" element={<div>detail-page</div>} />
      </Routes>
    </MemoryRouter>
  );
}

describe("PickerQueuePage", () => {
  it("пустая очередь показывает пустое состояние", () => {
    renderQueue("queue", pickerValue());
    expect(screen.getByText("В очереди пусто")).toBeInTheDocument();
  });

  it("в mine-режиме показывает пустое состояние моей работы", () => {
    renderQueue("mine", pickerValue());
    expect(screen.getByText("Вы не взяли ни одного заказа")).toBeInTheDocument();
  });

  it("рисует карточку заказа и берёт его по кнопке", async () => {
    const take = vi.fn();
    renderQueue("queue", pickerValue({ queue: [order()], take }));

    expect(screen.getByText("P-0007")).toBeInTheDocument();
    expect(screen.getAllByText("Сборка").length).toBeGreaterThan(0);
    await userEvent.click(screen.getByRole("button", { name: "Взять" }));
    expect(take).toHaveBeenCalledWith(7);
  });

  it("клик по карточке ведёт в детали", async () => {
    renderQueue("queue", pickerValue({ queue: [order()] }));
    await userEvent.click(screen.getByText("P-0007"));
    expect(await screen.findByText("detail-page")).toBeInTheDocument();
  });

  it("фильтрует по типу работы", async () => {
    const queue = [
      order({ id: 1, order_number: "P-0001", job_type: "source" }),
      order({ id: 2, order_number: "P-0002", job_type: "consolidation" }),
    ];
    renderQueue("queue", pickerValue({ queue }));

    await userEvent.click(screen.getByRole("tab", { name: "Объединение" }));
    expect(screen.getByText("P-0002")).toBeInTheDocument();
    expect(screen.queryByText("P-0001")).not.toBeInTheDocument();
  });
});
