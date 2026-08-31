import { beforeEach, describe, expect, it } from "vitest";
import { courierReducer, createCourierPwaState } from "./reducer";
import { deliveryFactory, paginationMeta } from "./test/deliveryFactory";
import { persistCourierState, restoreCourierState } from "./cache";

describe("courier offline snapshot", () => {
  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem("auth_user_cache", JSON.stringify({ id: 9 }));
  });

  it("после перезапуска без сети показывает последний подтверждённый список", () => {
    const delivery = deliveryFactory({ id: 55 });
    const onlineState = courierReducer(createCourierPwaState(true), {
      type: "list/received",
      key: "mine",
      deliveries: [delivery],
      meta: paginationMeta(),
      append: false,
      receivedAt: 1234,
    });
    persistCourierState(onlineState);

    const offlineState = restoreCourierState(false);

    expect(offlineState.online).toBe(false);
    expect(offlineState.lists.mine.ids).toEqual([55]);
    expect(offlineState.deliveries[55]).toEqual(delivery);
    expect(offlineState.commands).toEqual({});
    expect(offlineState.notice).toBeNull();
  });
});
