import { useEffect } from "react";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { deliveryFactory, paginationMeta } from "./test/deliveryFactory";

const mocks = vi.hoisted(() => ({
  fetchCourierDeliveries: vi.fn(),
  fetchCourierDelivery: vi.fn(),
  claimCourierDelivery: vi.fn(),
  releaseCourierDelivery: vi.fn(),
  startCourierDelivery: vi.fn(),
  deliverCourierDelivery: vi.fn(),
  online: true,
}));

vi.mock("./api", () => mocks);
vi.mock("./useOnlineStatus", () => ({ useOnlineStatus: () => mocks.online }));

import { CourierProvider } from "./CourierContext";
import { useCourier } from "./useCourier";

function Probe() {
  const { queue, mine, refreshList, claim, state } = useCourier();
  useEffect(() => {
    void refreshList("queue", { status: "ready_for_delivery" });
  }, [refreshList]);
  return (
    <div>
      <span>queue:{queue.length}</span>
      <span>mine:{mine.length}</span>
      <span>notice:{state.notice?.message ?? "none"}</span>
      <button onClick={() => void claim(17)}>claim</button>
    </div>
  );
}

function OfflineProbe() {
  const { claim, state } = useCourier();
  return (
    <div>
      <span>{state.online ? "online" : "offline"}</span>
      <button onClick={() => void claim(17).catch(() => undefined)}>claim offline</button>
    </div>
  );
}

beforeEach(() => {
  localStorage.clear();
  Object.values(mocks).forEach((value) => {
    if (typeof value === "function" && "mockReset" in value) value.mockReset();
  });
  mocks.online = true;
  mocks.fetchCourierDeliveries.mockResolvedValue({ data: [], meta: paginationMeta({ total: 0 }) });
});

describe("CourierProvider", () => {
  it("после claim сохраняет серверный объект и сверяет Queue/Mine", async () => {
    const available = deliveryFactory();
    const assigned = deliveryFactory({
      courier: { id: 9, name: "Курьер", phone: null },
      actions: { can_claim: false, can_release: true, can_start: true, can_deliver: false },
    });
    mocks.fetchCourierDeliveries
      .mockResolvedValueOnce({ data: [available], meta: paginationMeta() })
      .mockResolvedValueOnce({ data: [], meta: paginationMeta({ total: 0 }) })
      .mockResolvedValueOnce({ data: [assigned], meta: paginationMeta() });
    mocks.claimCourierDelivery.mockResolvedValue({
      message: "Доставка назначена курьеру",
      data: assigned,
    });

    render(<CourierProvider><Probe /></CourierProvider>);
    expect(await screen.findByText("queue:1")).toBeInTheDocument();
    await userEvent.click(screen.getByRole("button", { name: "claim" }));

    expect(await screen.findByText("mine:1")).toBeInTheDocument();
    expect(screen.getByText("notice:Доставка назначена курьеру")).toBeInTheDocument();
    await waitFor(() => expect(mocks.fetchCourierDeliveries).toHaveBeenCalledTimes(3));
  });

  it("offline не отправляет POST-команду изменения статуса", async () => {
    mocks.online = false;
    render(<CourierProvider><OfflineProbe /></CourierProvider>);

    expect(screen.getByText("offline")).toBeInTheDocument();
    await userEvent.click(screen.getByRole("button", { name: "claim offline" }));

    expect(mocks.claimCourierDelivery).not.toHaveBeenCalled();
    expect(await screen.findByText("offline")).toBeInTheDocument();
  });
});
