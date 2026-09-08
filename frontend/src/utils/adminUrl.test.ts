import { describe, expect, it } from "vitest";
import { isLocalHostname } from "./adminUrl";

describe("admin url host", () => {
  it.each(["localhost", "127.0.0.1", "::1"])("считает %s локальным адресом", (hostname) => {
    expect(isLocalHostname(hostname)).toBe(true);
  });

  it("не направляет внешний HTTPS-хост на закрытый порт backend", () => {
    expect(isLocalHostname("tea-demo.trycloudflare.com")).toBe(false);
  });
});
