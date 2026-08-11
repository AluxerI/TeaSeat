import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import type { PickerOrder } from "../../picker/types";
import PickerTransfersPage from "./PickerTransfersPage";

const context = vi.hoisted(() => ({
  usePicker: vi.fn(),
}));

const apiMocks = vi.hoisted(() => ({
  fetchIncomingTransfers: vi.fn(),
}));

vi.mock("../../picker/PickerContext", () => ({
  usePicker: context.usePicker,
}));

vi.mock("../../picker/api", () => ({
  fetchIncomingTransfers: apiMocks.fetchIncomingTransfers,
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

function transfersBody(orders: PickerOrder[]) {
  return {
    data: orders,
    meta: { current_page: 1, last_page: 1, per_page: 50, total: orders.length },
  };
}

beforeEach(() => {
  apiMocks.fetchIncomingTransfers.mockReset();
  context.usePicker.mockReset();
});

describe("PickerTransfersPage", () => {
  it("пустой список показывает пустое состояние", async () => {
    apiMocks.fetchIncomingTransfers.mockResolvedValue(transfersBody([]));
    context.usePicker.mockReturnValue({ receive: vi.fn() });

    render(<PickerTransfersPage />);

    expect(await screen.findByText("Входящих трансферов нет")).toBeInTheDocument();
  });

  it("рисует карточку трансфера с маршрутом", async () => {
    apiMocks.fetchIncomingTransfers.mockResolvedValue(transfersBody([order()]));
    context.usePicker.mockReturnValue({ receive: vi.fn() });

    render(<PickerTransfersPage />);

    expect(await screen.findByText("T-0005")).toBeInTheDocument();
    expect(screen.getByText("Объединение")).toBeInTheDocument();
    expect(screen.getByText(/Островок/)).toBeInTheDocument();
    expect(screen.getByText(/Флагман/)).toBeInTheDocument();
  });

  it("кнопка «Принять» вызывает receive и обновляет список", async () => {
    const receive = vi.fn().mockResolvedValue(undefined);
    apiMocks.fetchIncomingTransfers
      .mockResolvedValueOnce(transfersBody([order()]))
      .mockResolvedValueOnce(transfersBody([]));
    context.usePicker.mockReturnValue({ receive });

    render(<PickerTransfersPage />);

    await screen.findByText("T-0005");
    await userEvent.click(screen.getByRole("button", { name: "Принять" }));

    await waitFor(() => expect(receive).toHaveBeenCalledWith(5));
    expect(await screen.findByText("Входящих трансферов нет")).toBeInTheDocument();
  });

  it("показывает ошибку при сбое загрузки", async () => {
    apiMocks.fetchIncomingTransfers.mockRejectedValue(new Error("Нет связи с сервером"));
    context.usePicker.mockReturnValue({ receive: vi.fn() });

    render(<PickerTransfersPage />);

    expect(await screen.findByText("Нет связи с сервером")).toBeInTheDocument();
  });
});
