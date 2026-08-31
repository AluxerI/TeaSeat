import { expect, test } from "@playwright/test";
import { customerOrder, mockCustomerAuth, mockCustomerCommerce } from "./mockCustomer";

// Sanity: подарок отправляется группой, снятый обычный товар не попадает в checkout.
test("@sanity выборочный checkout оставляет обычный товар и отправляет подарок целиком", async ({ page }) => {
  await mockCustomerAuth(page);
  let checkoutBody: any = null;
  await mockCustomerCommerce(page, async (body, route) => { checkoutBody = body; await route.fulfill({ json: { data: customerOrder } }); });
  await page.goto("/cart");
  await page.getByLabel(/Выбрать всё/).click();
  await page.getByLabel("Выбрать подарок Чайный вечер").click();
  await page.getByRole("button", { name: "Оформить выбранное" }).click();
  await page.getByRole("button", { name: "Подтвердить заказ" }).click();
  await expect.poll(() => checkoutBody).not.toBeNull();
  expect(checkoutBody.cart_item_ids).toEqual([]);
  expect(checkoutBody.cart_gift_ids).toEqual([22]);
});
