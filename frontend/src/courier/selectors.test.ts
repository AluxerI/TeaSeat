import { describe, expect, it } from "vitest";
import { deliveryFactory } from "./test/deliveryFactory";
import {
  buildYandexMapsUrl,
  deliveryDestination,
  selectAvailableDeliveries,
  selectMineGroups,
} from "./selectors";

describe("courier selectors", () => {
  it("оставляет в очереди только задания с серверным can_claim", () => {
    const available = deliveryFactory({ id: 1 });
    const assigned = deliveryFactory({
      id: 2,
      actions: { can_claim: false, can_release: true, can_start: true, can_deliver: false },
    });
    expect(selectAvailableDeliveries([available, assigned])).toEqual([available]);
  });

  it("группирует Mine без придумывания статуса completed", () => {
    const ready = deliveryFactory({ id: 1 });
    const shipped = deliveryFactory({ id: 2, status: "shipped" });
    const awaiting = deliveryFactory({ id: 3, status: "awaiting_receipt" });
    const groups = selectMineGroups([ready, shipped, awaiting]);
    expect(groups.shipped).toEqual([shipped]);
    expect(groups.ready_for_delivery).toEqual([ready]);
    expect(groups.awaiting_receipt).toEqual([awaiting]);
  });

  it("для трансфера возвращает склад назначения", () => {
    const transfer = deliveryFactory({
      delivery_kind: "transfer",
      delivery_address: null,
      transfer_destination: {
        warehouse_id: 4,
        name: "Центральный склад",
        city: "Москва",
        address: "Тверская, 5",
      },
    });
    expect(deliveryDestination(transfer)).toBe("Москва, Тверская, 5");
  });

  it("безопасно кодирует адрес для Яндекс Карт", () => {
    expect(buildYandexMapsUrl("Москва, Тверская 5")).toContain(
      encodeURIComponent("Москва, Тверская 5"),
    );
  });
});
