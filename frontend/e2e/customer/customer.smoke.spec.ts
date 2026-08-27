import { expect, test } from "@playwright/test";
import { mockCustomerAuth, mockCustomerCommerce } from "./mockCustomer";

// Smoke: основной путь от готовой корзины до созданного заказа.
test("@smoke покупатель оформляет выбранную корзину", async ({ page }) => {
  await mockCustomerAuth(page);
  await mockCustomerCommerce(page);
  await page.goto("/cart");
  await expect(page.getByText("Ассам")).toBeVisible();
  await page.getByRole("button", { name: "Оформить выбранное" }).click();
  await expect(page.getByRole("heading", { name: "Оформление заказа" })).toBeVisible();
  await page.getByRole("button", { name: "Подтвердить заказ" }).click();
  await expect(page.getByRole("heading", { name: "Заказ №TE-000091" })).toBeVisible();
});
