import { beforeEach, describe, expect, it } from "vitest";
import {
  clearStaffSnapshots,
  readStaffSnapshot,
  writeStaffSnapshot,
} from "./staffSnapshot";

function authenticateAs(id: number) {
  localStorage.setItem("auth_user_cache", JSON.stringify({ id }));
}

describe("staffSnapshot", () => {
  beforeEach(() => localStorage.clear());

  it("в офлайне возвращает последний снимок текущего сотрудника", () => {
    authenticateAs(7);
    writeStaffSnapshot("picker", { orders: [101] });
    expect(readStaffSnapshot("picker")).toEqual({ orders: [101] });
  });

  it("не показывает снимок другому сотруднику", () => {
    authenticateAs(7);
    writeStaffSnapshot("courier", { deliveries: [55] });
    authenticateAs(8);
    expect(readStaffSnapshot("courier")).toBeNull();
  });

  it("очищает все staff-снимки, но не затрагивает посторонние настройки", () => {
    authenticateAs(7);
    writeStaffSnapshot("picker", { orders: [101] });
    writeStaffSnapshot("courier", { deliveries: [55] });
    localStorage.setItem("theme", "tea");

    clearStaffSnapshots();

    expect(readStaffSnapshot("picker")).toBeNull();
    expect(readStaffSnapshot("courier")).toBeNull();
    expect(localStorage.getItem("theme")).toBe("tea");
  });
});
