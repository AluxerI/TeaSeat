import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, cleanup } from "@testing-library/react";
import App from "./App";

vi.mock("./api/api", () => ({
  default: {
    get: vi.fn().mockRejectedValue(new Error("no network")),
    post: vi.fn().mockRejectedValue(new Error("no network")),
  },
}));

const store = new Map<string, string>();
vi.stubGlobal("localStorage", {
  getItem: (k: string) => store.get(k) ?? null,
  setItem: (k: string, v: string) => void store.set(k, v),
  removeItem: (k: string) => void store.delete(k),
  clear: () => store.clear(),
  key: () => null,
  length: 0,
});

beforeEach(() => {
  cleanup();
  window.history.pushState({}, "", "/seller");
});

afterEach(() => {
  cleanup();
});

describe("App routing: seller does NOT redirect to catalog", () => {
  it("renders seller layout at /seller (no redirect)", async () => {
    render(<App />);
    const text = await screen.findAllByText(/Загрузка рабочей точки|Дашборд|Новый заказ|Заказы/i);
    expect(text.length).toBeGreaterThan(0);
    expect(window.location.pathname).toBe("/seller");
    expect(screen.queryByText(/Чайные посиделки/i)).toBeNull();
  });

  it("picker renders at /picker (no redirect)", async () => {
    window.history.pushState({}, "", "/picker");
    render(<App />);
    const text = await screen.findAllByText(/Загрузка очереди|Выберите склад|Сборка|Очередь/i);
    expect(text.length).toBeGreaterThan(0);
    expect(window.location.pathname).toBe("/picker");
  });

  it("catch-all still redirects garbage to /catalog", async () => {
    window.history.pushState({}, "", "/no-such-route");
    render(<App />);
    const text = await screen.findAllByText(/Каталог/i);
    expect(text.length).toBeGreaterThan(0);
    expect(window.location.pathname).toBe("/catalog");
  });
});
