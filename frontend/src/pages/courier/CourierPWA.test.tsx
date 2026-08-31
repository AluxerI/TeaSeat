import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter, Route, Routes } from "react-router-dom";
import { describe, expect, it, vi } from "vitest";
import { createCourierPwaState } from "../../courier/reducer";
import { deliveryFactory, paginationMeta } from "../../courier/test/deliveryFactory";
import CourierPWA from "./CourierPWA";

const mocks = vi.hoisted(() => ({ useCourier: vi.fn() }));
vi.mock("../../courier/useCourier", () => ({ useCourier: mocks.useCourier }));
vi.mock("../../courier/useCourierPolling", () => ({ useCourierPolling: vi.fn() }));
vi.mock("../../courier/usePageVisibility", () => ({ usePageVisibility: () => true }));

function courierValue() {
  const delivery = deliveryFactory();
  const state = createCourierPwaState(true);
  state.deliveries[delivery.id] = delivery;
  state.lists.queue = {
    ids: [delivery.id],
    phase: "success",
    error: null,
    meta: paginationMeta(),
    lastFetchedAt: Date.now(),
  };
  return {
    state,
    queue: [delivery],
    refreshList: vi.fn().mockResolvedValue(undefined),
    loadNextPage: vi.fn().mockResolvedValue(undefined),
    claim: vi.fn().mockResolvedValue(delivery),
  };
}

describe("CourierPWA", () => {
  it("показывает backend delivery и вызывает claim", async () => {
    const value = courierValue();
    mocks.useCourier.mockReturnValue(value);
    render(
      <MemoryRouter initialEntries={["/courier"]}>
        <Routes>
          <Route path="/courier" element={<CourierPWA />} />
          <Route path="/courier/deliveries/:id" element={<div>details</div>} />
        </Routes>
      </MemoryRouter>,
    );
    expect(screen.getByText("TE-000017")).toBeInTheDocument();
    expect(screen.getByText(/Невский проспект, 17/)).toBeInTheDocument();
    await userEvent.click(screen.getByRole("button", { name: "Взять доставку" }));
    expect(value.claim).toHaveBeenCalledWith(17);
  });

  it("хранит выбранный тип в URL", async () => {
    mocks.useCourier.mockReturnValue(courierValue());
    render(<MemoryRouter initialEntries={["/courier"]}><CourierPWA /></MemoryRouter>);
    await userEvent.click(screen.getByRole("tab", { name: "Клиентам" }));
    expect(screen.getByRole("tab", { name: "Клиентам" })).toHaveAttribute("aria-selected", "true");
  });
});
