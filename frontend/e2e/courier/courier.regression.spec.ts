import { expect, test } from "@playwright/test";
import { availableDelivery, fulfillList, mockCourierAuth } from "./mockCourier";

test("@regression конфликт claim не оставляет ложное назначение", async ({ page }) => {
  await mockCourierAuth(page);
  await page.route("**/api/courier/deliveries**", async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    if (request.method() === "POST" && url.pathname.endsWith("/claim")) {
      return route.fulfill({
        status: 409,
        json: {
          message: "Доставку уже забрал другой курьер",
          code: "delivery_transition_rejected",
        },
      });
    }
    if (/\/deliveries\/17$/.test(url.pathname)) {
      return route.fulfill({ status: 404, json: { message: "Доставка не найдена" } });
    }
    return fulfillList(route, request.method() === "GET" ? [availableDelivery] : []);
  });

  await page.goto("/courier");
  await page.getByRole("button", { name: "Взять доставку" }).click();
  await expect(page.getByText("Доставку уже забрал другой курьер")).toBeVisible();
  await expect(page).toHaveURL(/\/courier$/);
});
