import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { render, screen, cleanup } from "@testing-library/react";
import App from "./App";

vi.mock("./api/api", () => ({
  api: {
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
  store.clear();
  window.history.pushState({}, "", "/seller");
});

afterEach(() => {
  cleanup();
});

function cacheUser(permission: string) {
  store.set("auth_token", "test-token");
  store.set(
    "auth_user_cache",
    JSON.stringify({ id: 1, permissions: [permission], roles: [] })
  );
}

describe("App routing: staff sections are protected", () => {
  it("opens seller PWA offline for the last authenticated seller", async () => {
    cacheUser("create seller orders");
    render(<App />);
    const text = await screen.findAllByText(/Загрузка рабочей точки|Выберите рабочую точку/i);
    expect(text.length).toBeGreaterThan(0);
    expect(window.location.pathname).toBe("/seller");
    expect(screen.queryByText(/Чайные посиделки/i)).toBeNull();
  });

  it("opens picker for a user with picker permission", async () => {
    cacheUser("view picking orders");
    window.history.pushState({}, "", "/picker");
    render(<App />);
    const text = await screen.findAllByText(/Загрузка очереди|Выберите склад|Сборка|Очередь/i);
    expect(text.length).toBeGreaterThan(0);
    expect(window.location.pathname).toBe("/picker");
  });

  it("redirects an anonymous user from seller to login", async () => {
    render(<App />);
    expect(await screen.findByRole("heading", { name: "Войти" })).toBeInTheDocument();
    expect(window.location.pathname).toBe("/login");
  });

  it("catch-all still redirects garbage to /catalog", async () => {
    window.history.pushState({}, "", "/no-such-route");
    render(<App />);
    const text = await screen.findAllByText(/Каталог/i);
    expect(text.length).toBeGreaterThan(0);
    expect(window.location.pathname).toBe("/catalog");
  });
});
