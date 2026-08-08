import axios, { AxiosError } from "axios";
import { api } from "../api/api";
import { ensureDeviceUuid, readSession, writeSession } from "./db";
import type {
  ApiBootstrap,
  ApiSellerOrder,
  ApiSyncResponse,
  OutboxAction,
  PaymentMethod,
} from "./types";

/** Ошибка seller-API, разобранная по таблице статусов staff-api-contract.md.
 *  `kind` — то, по чему ветвится интерфейс; `message` — то, что видит продавец. */
export type SellerErrorKind =
  | "offline"
  | "unauthorized"
  | "forbidden"
  | "not_found"
  | "conflict"
  | "rejected"
  | "throttled"
  | "server";

export class SellerApiError extends Error {
  constructor(
    public kind: SellerErrorKind,
    message: string,
    public status?: number,
    public code?: string,
    public fields?: Record<string, string[]>
  ) {
    super(message);
    this.name = "SellerApiError";
  }

  /** Есть ли смысл повторить то же самое событие позже без правок. */
  get retryable(): boolean {
    return this.kind === "offline" || this.kind === "throttled" || this.kind === "server";
  }
}

export function toSellerError(error: unknown): SellerApiError {
  if (error instanceof SellerApiError) return error;
  if (!axios.isAxiosError(error)) {
    return new SellerApiError("server", (error as Error)?.message ?? "Неизвестная ошибка");
  }

  const axiosError = error as AxiosError<{
    message?: string;
    code?: string;
    errors?: Record<string, string[]>;
  }>;
  const status = axiosError.response?.status;
  const body = axiosError.response?.data;
  const message = body?.message ?? axiosError.message;

  if (!axiosError.response) {
    return new SellerApiError("offline", "Нет связи с сервером", undefined, undefined);
  }

  switch (status) {
    case 401:
      return new SellerApiError("unauthorized", "Сессия истекла, войдите заново", status);
    case 403:
      return new SellerApiError(
        "forbidden",
        body?.message ?? "Нет доступа к этой рабочей точке",
        status,
        body?.code
      );
    case 404:
      return new SellerApiError("not_found", body?.message ?? "Объект не найден", status);
    case 409:
      return new SellerApiError("conflict", body?.message ?? "Состояние изменилось", status, body?.code);
    case 422:
      return new SellerApiError("rejected", message ?? "Операция отклонена", status, body?.code, body?.errors);
    case 429:
      return new SellerApiError("throttled", "Слишком много запросов, повторите позже", status);
    default:
      return new SellerApiError("server", message ?? "Ошибка сервера", status);
  }
}

/** Все seller-маршруты требуют X-Device-UUID; забыть его — значит получить 422
 *  на каждом запросе, поэтому заголовок ставится централизованно. */
async function deviceHeaders(): Promise<Record<string, string>> {
  return { "X-Device-UUID": await ensureDeviceUuid() };
}

export async function registerDevice(
  name: string,
  warehouseId: number
): Promise<void> {
  const deviceUuid = await ensureDeviceUuid();
  try {
    await api.post(
      "/api/seller/devices/register",
      { device_uuid: deviceUuid, name, warehouse_id: warehouseId },
      { headers: { "X-Device-UUID": deviceUuid } }
    );
    await writeSession({ device_name: name });
  } catch (error) {
    throw toSellerError(error);
  }
}

/** Bootstrap: каталог точки, подписанные цены и серверное время.
 *  Побочный эффект — обновление `clock_offset`: часы кассового планшета уезжают,
 *  а backend отклоняет `occurred_at` из будущего. */
export async function fetchBootstrap(warehouseId: number): Promise<ApiBootstrap> {
  try {
    const requestedAt = Date.now();
    const res = await api.get<{ data: ApiBootstrap }>("/api/seller/bootstrap", {
      params: { warehouse_id: warehouseId },
      headers: await deviceHeaders(),
    });
    const data = res.data.data;
    const serverTime = Date.parse(data.server_time);
    if (Number.isFinite(serverTime)) {
      await writeSession({ clock_offset: serverTime - requestedAt });
    }
    return data;
  } catch (error) {
    throw toSellerError(error);
  }
}

export interface SyncEventPayload {
  event_id: string;
  action: OutboxAction;
  client_order_id?: string;
  order_id?: number;
  revision: number;
  warehouse_id?: number;
  occurred_at?: string;
  payment_method?: PaymentMethod;
  items?: Array<{ product_id: number; quantity: number; pricing_token: string }>;
}

export async function postSync(events: SyncEventPayload[]): Promise<ApiSyncResponse> {
  try {
    const res = await api.post<ApiSyncResponse>(
      "/api/seller/sync",
      { events },
      { headers: await deviceHeaders() }
    );
    return res.data;
  } catch (error) {
    throw toSellerError(error);
  }
}

export async function fetchSellerOrders(params: {
  status?: string;
  per_page?: number;
} = {}): Promise<ApiSellerOrder[]> {
  try {
    const res = await api.get<{ data: ApiSellerOrder[] }>("/api/seller/orders", {
      params,
      headers: await deviceHeaders(),
    });
    return res.data.data ?? [];
  } catch (error) {
    throw toSellerError(error);
  }
}

/** Скорректированное серверное время. Используется для `occurred_at`. */
export async function serverNow(): Promise<Date> {
  const { clock_offset } = await readSession();
  return new Date(Date.now() + clock_offset);
}
