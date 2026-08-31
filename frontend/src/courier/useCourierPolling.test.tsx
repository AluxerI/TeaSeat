import { act, render } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { useCourierPolling } from "./useCourierPolling";

function Probe({ refresh, enabled = true }: { refresh: () => Promise<void>; enabled?: boolean }) {
  useCourierPolling({ enabled, intervalMs: 30_000, refresh });
  return null;
}

afterEach(() => vi.useRealTimers());

describe("useCourierPolling", () => {
  it("делает первый запрос сразу и следующий только после ожидания", async () => {
    vi.useFakeTimers();
    const refresh = vi.fn().mockResolvedValue(undefined);
    render(<Probe refresh={refresh} />);
    await act(async () => Promise.resolve());
    expect(refresh).toHaveBeenCalledTimes(1);

    await act(async () => {
      await vi.advanceTimersByTimeAsync(29_999);
    });
    expect(refresh).toHaveBeenCalledTimes(1);
    await act(async () => {
      await vi.advanceTimersByTimeAsync(1);
    });
    expect(refresh).toHaveBeenCalledTimes(2);
  });

  it("не запускается, когда polling выключен", async () => {
    const refresh = vi.fn().mockResolvedValue(undefined);
    render(<Probe refresh={refresh} enabled={false} />);
    await act(async () => Promise.resolve());
    expect(refresh).not.toHaveBeenCalled();
  });
});
