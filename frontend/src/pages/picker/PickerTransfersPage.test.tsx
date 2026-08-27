import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import type { PickerOrder } from "../../picker/types";
import PickerTransfersPage from "./PickerTransfersPage";

const context = vi.hoisted(() => ({
  usePicker: vi.fn(),
}));

vi.mock("../../picker/PickerContext", () => ({
  usePicker: context.usePicker,
}));

function order(overrides: Partial<PickerOrder> = {}): PickerOrder {
  return {
    id: 5,
    order_number: "T-0005",
    customer_order_id: 21,
    customer_order_number: "A-0021",
    job_type: "consolidation",
    status: "awaiting_receipt",
    status_name: "Ожидает получения",
    warehouse: { id: 2, name: "Островок" },
    destination_warehouse: { id: 1, name: "Флагман" },
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
      can_take: false,
      can_release: false,
      can_complete: false,
      can_escalate: false,
      can_report_shortage: false,
      can_receive: true,
    },
    ...overrides,
  };
}

function pickerValue(overrides: Record<string, unknown> = {}) {
  return {
    receive: vi.fn(),
    transfers: [],
    transfersLoading: false,
    transfersError: null,
    refreshTransfers: vi.fn().mockResolvedValue(undefined),
    online: true,
    ...overrides,
  };
}

beforeEach(() => {
  context.usePicker.mockReset();
});

describe("PickerTransfersPage", () => {
  it("пустой список показывает пустое состояние", async () => {
    context.usePicker.mockReturnValue(pickerValue());

    render(<PickerTransfersPage />);

    expect(await screen.findByText("Входящих трансферов нет")).toBeInTheDocument();
  });

  it("рисует карточку трансфера с маршрутом", async () => {
    context.usePicker.mockReturnValue(pickerValue({ transfers: [order()] }));

    render(<PickerTransfersPage />);

    expect(await screen.findByText("T-0005")).toBeInTheDocument();
    expect(screen.getByText("Объединение")).toBeInTheDocument();
    expect(screen.getByText(/Островок/)).toBeInTheDocument();
    expect(screen.getByText(/Флагман/)).toBeInTheDocument();
  });

  it("кнопка «Принять» вызывает Context-команду", async () => {
    const receive = vi.fn().mockResolvedValue(undefined);
    context.usePicker.mockReturnValue(pickerValue({ receive, transfers: [order()] }));

    render(<PickerTransfersPage />);

    await screen.findByText("T-0005");
    await userEvent.click(screen.getByRole("button", { name: "Принять" }));

    await waitFor(() => expect(receive).toHaveBeenCalledWith(5));
  });

  it("показывает ошибку при сбое загрузки", async () => {
    context.usePicker.mockReturnValue(
      pickerValue({ transfersError: "Нет связи с сервером" }),
    );

    render(<PickerTransfersPage />);

    expect(await screen.findByText("Нет связи с сервером")).toBeInTheDocument();
  });

  it("offline оставляет сохранённые карточки, но выключает обновление и приёмку", () => {
    context.usePicker.mockReturnValue(
      pickerValue({ transfers: [order()], online: false }),
    );

    render(<PickerTransfersPage />);

    expect(screen.getByText("T-0005")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Обновить" })).toBeDisabled();
    expect(screen.getByRole("button", { name: "Принять" })).toBeDisabled();
  });
});
