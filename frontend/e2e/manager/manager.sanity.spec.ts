import { expect, test } from "@playwright/test";
import { fulfillIssueList, managerIssue, meta, mockManagerAuth, mockManagerCounters } from "./mockManager";

test("@sanity менеджер берёт складскую проблему в работу", async ({ page }) => {
  await mockManagerAuth(page);
  await mockManagerCounters(page);
  let issue = managerIssue;
  await page.route("**/api/manager/fulfillment-issues**", async (route) => {
    const url = new URL(route.request().url());
    if (url.pathname.endsWith("/affected-orders")) return route.fulfill({ json: { issue, data: [], summary: { candidate_orders_count: 0, reserved_quantity_total: 0, shortage_quantity: 100 }, meta: meta(0) } });
    if (route.request().method() === "POST" && url.pathname.endsWith("/take")) {
      issue = { ...issue, status: "in_review", status_name: "На рассмотрении", manager_id: 2, manager: { id: 2, name: "Тестовый менеджер" }, actions: { can_take: false, can_release: true, can_close: true, can_reopen: false } };
      return route.fulfill({ json: { message: "Дело взято в работу", data: issue } });
    }
    if (/\/fulfillment-issues\/6$/.test(url.pathname)) return route.fulfill({ json: { data: issue } });
    return fulfillIssueList(route, [issue]);
  });

  await page.goto("/manager/issues");
  await page.getByText("Ассам").first().click();
  await page.getByRole("button", { name: "Взять в работу" }).click();
  await page.getByRole("dialog").getByRole("button", { name: "Взять" }).click();
  await expect(page.getByText("Дело взято в работу")).toBeVisible();
  await expect(page.getByText("На рассмотрении")).toBeVisible();
});
