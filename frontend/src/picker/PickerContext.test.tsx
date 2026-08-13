import { useEffect } from "react";
import { render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({
  fetchWorkLocations: vi.fn(),
  fetchQueue: vi.fn(),
}));

vi.mock("../seller/bootstrap", () => ({
  fetchWorkLocations: mocks.fetchWorkLocations,
}));

vi.mock("./api", () => ({
  fetchQueue: mocks.fetchQueue,
  completeOrder: vi.fn(),
  escalateOrder: vi.fn(),
  fetchIncomingTransfers: vi.fn(),
  fetchOrder: vi.fn(),
  receiveTransfer: vi.fn(),
  releaseOrder: vi.fn(),
  reportShortage: vi.fn(),
  takeOrder: vi.fn(),
}));

import { PickerProvider, usePicker } from "./PickerContext";

function Probe() {
  const { init, ready, queue } = usePicker();
  useEffect(() => {
    void init();
  }, [init]);
  return <div>{ready ? `ready:${queue.length}` : "loading"}</div>;
}

beforeEach(() => {
  localStorage.clear();
  mocks.fetchWorkLocations.mockReset();
  mocks.fetchQueue.mockReset();
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
    expect(mocks.fetchQueue).toHaveBeenNthCalledWith(1, { warehouse_id: 7 });
    expect(mocks.fetchQueue).toHaveBeenNthCalledWith(2, {
      warehouse_id: 7,
      mine: true,
    });
  });
});
