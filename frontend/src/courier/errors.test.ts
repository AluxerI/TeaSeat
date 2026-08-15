import { describe, expect, it } from "vitest";
import { StaffApiError, toStaffApiError } from "./errors";

describe("StaffApiError", () => {
  it("разрешает retry только временных ошибок", () => {
    expect(new StaffApiError("offline", "offline").retryable).toBe(true);
    expect(new StaffApiError("server", "server", 500).retryable).toBe(true);
    expect(new StaffApiError("conflict", "conflict", 409).retryable).toBe(false);
  });

  it("сохраняет backend code и fields для 422", () => {
    const error = toStaffApiError({
      isAxiosError: true,
      message: "unprocessable",
      response: {
        status: 422,
        headers: {},
        data: {
          message: "Проверьте причину",
          code: "validation_failed",
          errors: { reason: ["Причина обязательна"] },
        },
      },
    });
    expect(error).toMatchObject({
      kind: "validation",
      code: "validation_failed",
      fields: { reason: ["Причина обязательна"] },
    });
  });
});
