import { act, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it } from "vitest";
import { useOnlineStatus } from "./useOnlineStatus";

function Probe() {
  return <span>{useOnlineStatus() ? "online" : "offline"}</span>;
}

afterEach(() => {
  Object.defineProperty(navigator, "onLine", { configurable: true, value: true });
});

describe("useOnlineStatus", () => {
  it("сразу читает navigator.onLine и реактивно принимает browser-события", () => {
    Object.defineProperty(navigator, "onLine", { configurable: true, value: false });
    render(<Probe />);
    expect(screen.getByText("offline")).toBeInTheDocument();

    act(() => window.dispatchEvent(new Event("online")));
    expect(screen.getByText("online")).toBeInTheDocument();

    act(() => window.dispatchEvent(new Event("offline")));
    expect(screen.getByText("offline")).toBeInTheDocument();
  });
});
