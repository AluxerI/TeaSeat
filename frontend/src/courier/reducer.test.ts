import { describe, expect, it } from "vitest";
import { courierReducer, createCourierPwaState } from "./reducer";
import { deliveryFactory, paginationMeta } from "./test/deliveryFactory";

describe("courierReducer", () => {
  it("не мутирует исходный state при запуске команды", () => {
    const state = createCourierPwaState(true);
    Object.freeze(state);
    Object.freeze(state.commands);

    const next = courierReducer(state, {
      type: "command/requested",
      deliveryId: 17,
      command: "claim",
    });

    expect(next).not.toBe(state);
    expect(next.commands).not.toBe(state.commands);
    expect(next.commands[17]).toEqual({ type: "claim", phase: "pending", error: null });
  });

  it("нормализует delivery и не дублирует ids при append", () => {
    const initial = createCourierPwaState(true);
    const first = courierReducer(initial, {
      type: "list/received",
      key: "history",
      deliveries: [deliveryFactory({ id: 1 })],
      meta: paginationMeta({ last_page: 2 }),
      append: false,
      receivedAt: 1,
    });
    const next = courierReducer(first, {
      type: "list/received",
      key: "history",
      deliveries: [deliveryFactory({ id: 1 }), deliveryFactory({ id: 2 })],
      meta: paginationMeta({ current_page: 2, last_page: 2 }),
      append: true,
      receivedAt: 2,
    });
    expect(next.lists.history.ids).toEqual([1, 2]);
    expect(Object.keys(next.deliveries)).toEqual(["1", "2"]);
  });
});
