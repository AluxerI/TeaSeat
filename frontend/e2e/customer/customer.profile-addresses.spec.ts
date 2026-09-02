import { expect, test } from "@playwright/test";
import { mockCustomerAuth, mockCustomerCommerce } from "./mockCustomer";

test("@regression ЛК показывает и сохраняет адрес доставки", async ({ page }) => {
  await mockCustomerAuth(page);
  await mockCustomerCommerce(page);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/profile");

  await expect(page.getByRole("heading", { name: "Адреса доставки" })).toBeVisible();
  await expect(page.getByText("101000, Москва, Тверская, 1")).toBeVisible();

  await page.getByRole("button", { name: "Добавить адрес" }).click();
  await page.getByLabel("Город").fill("Казань");
  await page.getByLabel("Улица, дом, квартира").fill("Баумана, 10");
  await page.getByLabel("Почтовый индекс").fill("420111");
  await page.getByRole("button", { name: "Сохранить адрес" }).click();

  await expect(page.getByText("Адрес доставки сохранён")).toBeVisible();
});
