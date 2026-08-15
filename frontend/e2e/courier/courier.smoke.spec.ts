import { expect, test } from "@playwright/test";
import { availableDelivery, fulfillList, mockCourierAuth } from "./mockCourier";

test("@smoke курьер открывает очередь и берёт доставку", async ({ page }) => {
  await mockCourierAuth(page);
  let assigned = false;
  const assignedDelivery = {
    ...availableDelivery,
    courier: { id: 9, name: "Тестовый курьер", phone: "+79991111111" },
    actions: { can_claim: false, can_release: true, can_start: true, can_deliver: false },
  };

  await page.route("**/api/courier/deliveries**", async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    if (request.method() === "POST" && url.pathname.endsWith("/claim")) {
      assigned = true;
      return route.fulfill({ json: { message: "Доставка назначена курьеру", data: assignedDelivery } });
    }
    if (request.method() !== "GET") return route.fallback();
    if (/\/deliveries\/17$/.test(url.pathname)) {
      return route.fulfill({ json: { data: assigned ? assignedDelivery : availableDelivery } });
    }
    if (url.searchParams.get("mine") === "1") {
      return fulfillList(route, assigned ? [assignedDelivery] : []);
    }
    return fulfillList(route, assigned ? [] : [availableDelivery]);
  });

  await page.goto("/courier");
  await expect(page.getByRole("heading", { name: "Свободные доставки" })).toBeVisible();
  await expect(page.getByText("TE-000017")).toBeVisible();
  await page.getByRole("button", { name: "Взять доставку" }).click();
  await expect(page.getByText("Доставка назначена курьеру")).toBeVisible();
  await page.getByRole("button", { name: "Мои" }).click();
  await expect(page.getByRole("heading", { name: "Мои доставки" })).toBeVisible();
  await expect(page.getByText("TE-000017")).toBeVisible();
});
