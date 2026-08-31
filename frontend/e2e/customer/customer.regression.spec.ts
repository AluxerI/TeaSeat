import { expect, test } from "@playwright/test";
import { mockCustomerAuth, mockCustomerCommerce } from "./mockCustomer";

// Regression: личный кабинет должен по-прежнему открывать карточку заказа.
test("@regression история заказов в ЛК открывает клиентскую карточку", async ({ page }) => {
  await mockCustomerAuth(page);
  await mockCustomerCommerce(page);
  await page.goto("/profile?section=orders");
  await expect(page.getByText("Заказ №TE-000091")).toBeVisible();
  await page.getByRole("button", { name: "Подробнее" }).click();
  await expect(page.getByRole("heading", { name: "Заказ №TE-000091" })).toBeVisible();
  await expect(page.getByText("Самовывоз")).toBeVisible();
});
