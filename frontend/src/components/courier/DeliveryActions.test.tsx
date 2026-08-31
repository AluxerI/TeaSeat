import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { deliveryFactory } from "../../courier/test/deliveryFactory";
import { DeliveryActions } from "./DeliveryActions";

describe("DeliveryActions", () => {
  it("не отправляет release без причины", async () => {
    const onRelease = vi.fn().mockResolvedValue(undefined);
    render(
      <DeliveryActions
        delivery={deliveryFactory({
          courier: { id: 1, name: "Курьер", phone: null },
          actions: { can_claim: false, can_release: true, can_start: false, can_deliver: false },
        })}
        online
        onRelease={onRelease}
        onStart={vi.fn()}
        onDeliver={vi.fn()}
      />,
    );
    await userEvent.click(screen.getByRole("button", { name: "Вернуть" }));
    await userEvent.click(screen.getByRole("button", { name: "Подтвердить" }));
    expect(await screen.findByText("Укажите причину возврата")).toBeInTheDocument();
    expect(onRelease).not.toHaveBeenCalled();
  });
});
