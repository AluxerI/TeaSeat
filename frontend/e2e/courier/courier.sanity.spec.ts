import { expect, test } from "@playwright/test";
import { availableDelivery, fulfillList, mockCourierAuth } from "./mockCourier";

test("@sanity назначенная доставка проходит start и deliver", async ({ page }) => {
  await mockCourierAuth(page);
  let current = {
    ...availableDelivery,
    courier: { id: 9, name: "Тестовый курьер", phone: "+79991111111" },
    actions: { can_claim: false, can_release: true, can_start: true, can_deliver: false },
  };

  await page.route("**/api/courier/deliveries**", async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    if (request.method() === "POST" && url.pathname.endsWith("/start")) {
      current = {
        ...current,
        status: "shipped",
        status_name: "Отправлен",
        actions: { can_claim: false, can_release: false, can_start: false, can_deliver: true },
      };
      return route.fulfill({ json: { message: "Доставка начата", data: current } });
    }
    if (request.method() === "POST" && url.pathname.endsWith("/deliver")) {
      current = {
        ...current,
        status: "delivered",
        status_name: "Доставлен",
        actions: { can_claim: false, can_release: false, can_start: false, can_deliver: false },
      };
      return route.fulfill({ json: { message: "Доставка завершена", data: current } });
    }
    if (/\/deliveries\/17$/.test(url.pathname)) {
      return route.fulfill({ json: { data: current } });
    }
    if (url.searchParams.get("status") === "delivered") {
      return fulfillList(route, current.status === "delivered" ? [current] : []);
    }
    if (url.searchParams.get("mine") === "1") return fulfillList(route, [current]);
    return fulfillList(route, []);
  });

  await page.goto("/courier/deliveries/17");
  await page.getByRole("button", { name: "Начать доставку" }).click();
  await page.getByRole("button", { name: "Подтвердить" }).click();
  await expect(page.getByText("Доставка начата")).toBeVisible();
  await page.getByRole("button", { name: "Доставлено" }).click();
  await page.getByRole("button", { name: "Подтвердить" }).click();
  await expect(page.getByText("Доставка завершена")).toBeVisible();
  await expect(page.getByText("Доставлен")).toBeVisible();
});
