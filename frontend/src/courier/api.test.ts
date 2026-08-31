import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));
vi.mock("../api/api", () => ({ api: mocks }));

import {
  fetchCourierDeliveries,
  releaseCourierDelivery,
} from "./api";
import { deliveryFactory, paginationMeta } from "./test/deliveryFactory";

beforeEach(() => {
  mocks.get.mockReset();
  mocks.post.mockReset();
});

describe("courier api", () => {
  it("передаёт backend-фильтры истории", async () => {
    mocks.get.mockResolvedValue({ data: { data: [], meta: paginationMeta({ total: 0 }) } });
    await fetchCourierDeliveries({ mine: true, status: "delivered", per_page: 20 });
    expect(mocks.get).toHaveBeenCalledWith("/api/courier/deliveries", {
      params: { mine: "1", status: "delivered", per_page: 20, page: 1 },
    });
  });

  it("обрезает пробелы в причине release", async () => {
    const delivery = deliveryFactory();
    mocks.post.mockResolvedValue({ data: { message: "Возвращено", data: delivery } });
    await releaseCourierDelivery(delivery.id, "  сломался велосипед  ");
    expect(mocks.post).toHaveBeenCalledWith(
      "/api/courier/deliveries/17/release",
      { reason: "сломался велосипед" },
    );
  });
});
