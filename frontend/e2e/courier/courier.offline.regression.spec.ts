import { expect, test } from "@playwright/test";
import { availableDelivery, fulfillList, mockCourierAuth } from "./mockCourier";

test("@regression @offline курьер после перезапуска читает сохранённую работу", async ({
  context,
  page,
}) => {
  await mockCourierAuth(page);
  const assignedDelivery = {
    ...availableDelivery,
    courier: { id: 9, name: "Тестовый курьер", phone: "+79991111111" },
    actions: {
      can_claim: false,
      can_release: true,
      can_start: true,
      can_deliver: false,
    },
  };

  await page.route("**/api/courier/deliveries**", async (route) => {
    if (route.request().method() !== "GET") return route.fallback();
    return fulfillList(route, [assignedDelivery]);
  });

  // Первый online-вход создаёт подтверждённый GET-снимок и устанавливает SW.
  await page.goto("/courier/mine");
  await expect(page.getByText("TE-000017")).toBeVisible();
  await page.evaluate(() => navigator.serviceWorker?.ready);

  // Имитируем закрытие/повторное открытие PWA без сети.
  await context.setOffline(true);
  await page.reload();

  await expect(page.getByText("Офлайн")).toBeVisible();
  await expect(page.getByText("TE-000017")).toBeVisible();
  await expect(page.getByRole("button", { name: "Обновить" })).toBeDisabled();
});
