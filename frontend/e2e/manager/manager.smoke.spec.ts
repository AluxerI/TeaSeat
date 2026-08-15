import { expect, test } from "@playwright/test";
import { managerOrder, meta, mockManagerAuth, mockManagerCounters } from "./mockManager";

test("@smoke менеджер открывает заказ и подтверждает его для Picker", async ({ page }) => {
  await mockManagerAuth(page);
  await mockManagerCounters(page);
  await page.route("**/api/manager/fulfillment-issues**", (route) => route.fulfill({ json: { data: [], summary: { waiting: 0, in_review: 0, closed: 0 }, meta: meta(0) } }));
  let order = managerOrder;
  await page.route("**/api/manager/orders**", async (route) => {
    const url = new URL(route.request().url());
    if (route.request().method() === "POST" && url.pathname.endsWith("/confirm")) {
      order = { ...order, status: "confirmed", status_name: "Подтверждён", actions: { ...order.actions, can_confirm: false } };
      return route.fulfill({ json: { message: "Заказ подтверждён", data: order } });
    }
    if (/\/orders\/41$/.test(url.pathname)) return route.fulfill({ json: { data: order } });
    return route.fulfill({ json: { data: [order], meta: meta(1) } });
  });

  await page.goto("/manager/orders");
  await expect(page.getByText("TE-000041")).toBeVisible();
  await page.getByText("TE-000041").click();
  await page.getByRole("button", { name: "Подтвердить" }).click();
  await page.getByRole("dialog").getByRole("button", { name: "Подтвердить" }).click();
  await expect(page.getByText("Заказ подтверждён")).toBeVisible();
  await expect(page.getByText("Подтверждён")).toBeVisible();
});
