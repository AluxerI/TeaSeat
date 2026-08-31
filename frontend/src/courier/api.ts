import { api } from "../api/api";
import { toStaffApiError } from "./errors";
import type {
  CourierDelivery,
  CourierDeliveryCommandResponse,
  CourierDeliveryFilters,
  CourierDeliveryListResponse,
} from "./types";

const DELIVERY_PATH = "/api/courier/deliveries";

function paramsFrom(filters: CourierDeliveryFilters): Record<string, string | number> {
  const params: Record<string, string | number> = {
    per_page: filters.per_page ?? 50,
    page: filters.page ?? 1,
  };
  if (filters.status) params.status = filters.status;
  if (filters.warehouse_id) params.warehouse_id = filters.warehouse_id;
  if (filters.delivery_kind) params.delivery_kind = filters.delivery_kind;
  if (filters.mine) params.mine = "1";
  return params;
}

export async function fetchCourierDeliveries(
  filters: CourierDeliveryFilters = {},
): Promise<CourierDeliveryListResponse> {
  try {
    const response = await api.get<CourierDeliveryListResponse>(DELIVERY_PATH, {
      params: paramsFrom(filters),
    });
    return response.data;
  } catch (error) {
    throw toStaffApiError(error);
  }
}

export async function fetchCourierDelivery(id: number): Promise<CourierDelivery> {
  try {
    const response = await api.get<{ data: CourierDelivery }>(`${DELIVERY_PATH}/${id}`);
    return response.data.data;
  } catch (error) {
    throw toStaffApiError(error);
  }
}

async function command(
  id: number,
  action: "claim" | "start" | "deliver",
): Promise<CourierDeliveryCommandResponse> {
  try {
    const response = await api.post<CourierDeliveryCommandResponse>(
      `${DELIVERY_PATH}/${id}/${action}`,
    );
    return response.data;
  } catch (error) {
    throw toStaffApiError(error);
  }
}

export const claimCourierDelivery = (id: number) => command(id, "claim");
export const startCourierDelivery = (id: number) => command(id, "start");
export const deliverCourierDelivery = (id: number) => command(id, "deliver");

export async function releaseCourierDelivery(
  id: number,
  reason: string,
): Promise<CourierDeliveryCommandResponse> {
  try {
    const response = await api.post<CourierDeliveryCommandResponse>(
      `${DELIVERY_PATH}/${id}/release`,
      { reason: reason.trim() },
    );
    return response.data;
  } catch (error) {
    throw toStaffApiError(error);
  }
}
