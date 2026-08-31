import { useEffect } from "react";
import { render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { writeStaffSnapshot } from "../pwa/staffSnapshot";
import type { PickerOrder } from "./types";

const mocks = vi.hoisted(() => ({
  fetchWorkLocations: vi.fn(),
  fetchQueue: vi.fn(),
  fetchIncomingTransfers: vi.fn(),
  takeOrder: vi.fn(),
}));

vi.mock("../seller/bootstrap", () => ({
  fetchWorkLocations: mocks.fetchWorkLocations,
}));

vi.mock("./api", () => ({
  fetchQueue: mocks.fetchQueue,
  completeOrder: vi.fn(),
  escalateOrder: vi.fn(),
  fetchIncomingTransfers: mocks.fetchIncomingTransfers,
  fetchOrder: vi.fn(),
  receiveTransfer: vi.fn(),
  releaseOrder: vi.fn(),
  reportShortage: vi.fn(),
  takeOrder: mocks.takeOrder,
}));

import { PickerProvider, usePicker } from "./PickerContext";

function Probe() {
  const { init, ready, queue, myOrders } = usePicker();
  useEffect(() => {
    void init();
  }, [init]);
  return (
    <div>
      <span>{ready ? `ready:${queue.length}` : "loading"}</span>
      <span>mine:{myOrders.length}</span>
    </div>
  );
}

beforeEach(() => {
  localStorage.clear();
  mocks.fetchWorkLocations.mockReset();
  mocks.fetchQueue.mockReset();
  mocks.fetchIncomingTransfers.mockReset();
  mocks.takeOrder.mockReset();
  Object.defineProperty(navigator, "onLine", { configurable: true, value: true });
});

describe("PickerProvider init", () => {
  it("загружает очереди именно для восстановленного склада", async () => {
    localStorage.setItem("picker_warehouse_id", "7");
    mocks.fetchWorkLocations.mockResolvedValue([
      { id: 7, name: "Склад 7", city: null, type: "warehouse" },
    ]);
    mocks.fetchQueue.mockResolvedValue({
      data: [{ id: 101 }],
      meta: { current_page: 1, last_page: 1, per_page: 50, total: 1 },
    });

    render(
      <PickerProvider>
        <Probe />
      </PickerProvider>
    );

    expect(await screen.findByText("ready:1")).toBeInTheDocument();
    await waitFor(() => expect(mocks.fetchQueue).toHaveBeenCalledTimes(2));
    expect(mocks.fetchQueue).toHaveBeenNthCalledWith(1, {
      warehouse_id: 7,
      status: "confirmed",
    });
    expect(mocks.fetchQueue).toHaveBeenNthCalledWith(2, {
      warehouse_id: 7,
      mine: true,
      status: "processing",
    });
  });

  it("при offline-start восстанавливает снимок и не теряет Мою работу", async () => {
    Object.defineProperty(navigator, "onLine", { configurable: true, value: false });
    localStorage.setItem("auth_user_cache", JSON.stringify({ id: 9 }));
    localStorage.setItem("picker_warehouse_id", "7");
    writeStaffSnapshot("picker", {
      workLocations: [{ id: 7, name: "Склад 7", city: null, type: "warehouse" }],
      warehouseId: 7,
      warehouseName: "Склад 7",
      queue: [],
      myOrders: [{ id: 101 }] as PickerOrder[],
      transfers: [],
      meta: null,
    });
    mocks.fetchWorkLocations.mockRejectedValue(new Error("Network Error"));

    render(
      <PickerProvider>
        <Probe />
      </PickerProvider>,
    );

    expect(await screen.findByText("ready:0")).toBeInTheDocument();
    expect(screen.getByText("mine:1")).toBeInTheDocument();
    expect(mocks.fetchQueue).not.toHaveBeenCalled();
  });
});
