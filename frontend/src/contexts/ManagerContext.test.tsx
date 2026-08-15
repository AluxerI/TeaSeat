import { render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({
  issues: vi.fn(),
  requests: vi.fn(),
  auth: vi.fn(),
}));

vi.mock("../manager/api", () => ({
  fetchFulfillmentIssues: mocks.issues,
  fetchManagerRequests: mocks.requests,
}));
vi.mock("../courier/useOnlineStatus", () => ({ useOnlineStatus: () => true }));
vi.mock("../courier/usePageVisibility", () => ({ usePageVisibility: () => true }));
vi.mock("../hooks/useAuth", () => ({ useAuth: mocks.auth }));

import { ManagerProvider, useManager } from "./ManagerContext";

function Probe() {
  const value = useManager();
  return <div>{value.warehouses.length}:{value.counters.issues}:{value.counters.requests}</div>;
}

describe("ManagerProvider", () => {
  beforeEach(() => {
    localStorage.clear();
    mocks.auth.mockReturnValue({ user: { work_locations: [{ id: 7, name: "Склад", type: "warehouse" }] } });
    mocks.issues.mockResolvedValue({ summary: { waiting: 2, in_review: 1, closed: 0 } });
    mocks.requests.mockResolvedValue({ summary: { waiting: 4, in_review: 2, resolved: 0, rejected: 0, withdrawn: 0 } });
  });

  it("держит только общие точки и счётчики очередей", async () => {
    render(<ManagerProvider><Probe /></ManagerProvider>);
    await waitFor(() => expect(screen.getByText("1:3:6")).toBeVisible());
  });
});
