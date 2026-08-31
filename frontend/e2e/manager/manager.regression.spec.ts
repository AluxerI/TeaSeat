import { expect, test } from "@playwright/test";
import { managerOrder, meta, mockManagerAuth, mockManagerCounters } from "./mockManager";

test("@regression 422 остаётся возле поля, а 429 блокирует повтор", async ({ page }) => {
  await mockManagerAuth(page);
  await mockManagerCounters(page);
  await page.route("**/api/manager/fulfillment-issues**", (route) => route.fulfill({ json: { data: [], summary: { waiting: 0, in_review: 0, closed: 0 }, meta: meta(0) } }));
  await page.route("**/api/manager/orders**", async (route) => {
    const url = new URL(route.request().url());
    if (route.request().method() === "POST" && url.pathname.endsWith("/cancel")) {
      return route.fulfill({ status: 422, json: { message: "The given data was invalid.", errors: { reason: ["Причина должна содержать минимум 3 символа"] } } });
    }
    if (route.request().method() === "POST" && url.pathname.endsWith("/internal-notes")) {
      return route.fulfill({ status: 429, headers: { "Retry-After": "5" }, json: { message: "Слишком много запросов" } });
    }
    if (/\/orders\/41$/.test(url.pathname)) return route.fulfill({ json: { data: managerOrder } });
    return route.fulfill({ json: { data: [managerOrder], meta: meta(1) } });
  });

  await page.goto("/manager/orders/41");
  await page.getByRole("button", { name: "Отменить" }).click();
  await page.getByLabel("Причина отмены").fill("нет");
  await page.getByRole("dialog").getByRole("button", { name: "Отменить заказ" }).click();
  await expect(page.getByText("Причина должна содержать минимум 3 символа")).toBeVisible();
  await page.getByRole("dialog").getByRole("button", { name: "Отмена" }).click();

  await page.getByRole("button", { name: "Добавить заметку" }).click();
  await page.getByLabel("Комментарий").fill("Проверено");
  await page.getByRole("dialog").getByRole("button", { name: "Добавить" }).click();
  await expect(page.getByText(/Повтор через 5 сек/)).toBeVisible();
  await expect(page.getByRole("dialog").getByRole("button", { name: "Добавить" })).toBeDisabled();
});
