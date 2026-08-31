import { describe, expect, it, vi } from "vitest";
import { fieldError, toStaffApiError } from "./errors";

function axiosError(status: number, data: object, headers: Record<string, string> = {}) {
  return { isAxiosError: true, response: { status, data, headers } };
}

describe("staff HTTP errors", () => {
  it("привязывает Laravel 422 к конкретному полю", () => {
    const error = toStaffApiError(axiosError(422, {
      message: "The given data was invalid.",
      errors: { reason: ["Причина обязательна"], quantity: ["Минимум 1"] },
    }));
    expect(error.kind).toBe("validation");
    expect(fieldError(error, "reason")).toBe("Причина обязательна");
    expect(fieldError(error, "missing")).toBeUndefined();
  });

  it("читает Retry-After в секундах", () => {
    const error = toStaffApiError(axiosError(429, {}, { "retry-after": "12" }));
    expect(error).toMatchObject({ kind: "throttled", retryAfterMs: 12_000 });
  });

  it("читает Retry-After как HTTP-дату", () => {
    vi.setSystemTime(new Date("2026-08-15T10:00:00Z"));
    const error = toStaffApiError(axiosError(429, {}, { "retry-after": "Sat, 15 Aug 2026 10:00:05 GMT" }));
    expect(error.retryAfterMs).toBe(5_000);
    vi.useRealTimers();
  });

  it("использует безопасную паузу, если proxy убрал rate-limit headers", () => {
    const error = toStaffApiError(axiosError(429, {}));
    expect(error.retryAfterMs).toBe(30_000);
  });

  it("сохраняет workflow code для 409", () => {
    const error = toStaffApiError(axiosError(409, {
      message: "Заказ уже собирают",
      code: "manager_order_transition_rejected",
    }));
    expect(error).toMatchObject({ kind: "conflict", code: "manager_order_transition_rejected" });
  });
});
