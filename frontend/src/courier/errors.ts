import axios, { AxiosError } from "axios";

export type StaffErrorKind =
  | "offline"
  | "timeout"
  | "unauthorized"
  | "forbidden"
  | "not_found"
  | "conflict"
  | "validation"
  | "throttled"
  | "server"
  | "bad_response"
  | "unknown";

/** Типизированная ошибка staff API. В отличие от interface её можно ловить
 * через `instanceof` и хранить вместе с исходным HTTP-кодом backend. */
export class StaffApiError extends Error {
  constructor(
    public kind: StaffErrorKind,
    message: string,
    public status?: number,
    public code?: string,
    public fields?: Record<string, string[]>,
    public retryAfterMs?: number,
  ) {
    super(message);
    this.name = "StaffApiError";
  }

  get retryable(): boolean {
    return ["offline", "timeout", "throttled", "server"].includes(this.kind);
  }
}

interface ErrorBody {
  message?: string;
  code?: string;
  errors?: Record<string, string[]>;
}

function retryAfterMs(error: AxiosError<ErrorBody>): number | undefined {
  const value = error.response?.headers["retry-after"];
  const seconds = Number(value);
  return Number.isFinite(seconds) ? seconds * 1000 : undefined;
}

export function toStaffApiError(error: unknown): StaffApiError {
  if (error instanceof StaffApiError) return error;
  if (!axios.isAxiosError(error)) {
    return new StaffApiError(
      "unknown",
      error instanceof Error ? error.message : "Неизвестная ошибка",
    );
  }

  const axiosError = error as AxiosError<ErrorBody>;
  if (axiosError.code === "ERR_CANCELED") {
    return new StaffApiError("unknown", "Запрос отменён");
  }
  if (axiosError.code === "ECONNABORTED" || axiosError.code === "ETIMEDOUT") {
    return new StaffApiError("timeout", "Сервер не ответил вовремя");
  }
  if (!axiosError.response) {
    return new StaffApiError("offline", "Нет связи с сервером");
  }

  const { status, data } = axiosError.response;
  switch (status) {
    case 401:
      return new StaffApiError("unauthorized", "Сессия истекла, войдите заново", status);
    case 403:
      return new StaffApiError(
        "forbidden",
        data?.message ?? "Нет доступа к этой доставке",
        status,
        data?.code,
      );
    case 404:
      return new StaffApiError("not_found", data?.message ?? "Доставка не найдена", status);
    case 409:
      return new StaffApiError(
        "conflict",
        data?.message ?? "Состояние доставки изменилось",
        status,
        data?.code,
      );
    case 422:
      return new StaffApiError(
        "validation",
        data?.message ?? "Проверьте введённые данные",
        status,
        data?.code,
        data?.errors,
      );
    case 429:
      return new StaffApiError(
        "throttled",
        "Слишком много запросов, повторите позже",
        status,
        data?.code,
        undefined,
        retryAfterMs(axiosError),
      );
    default:
      if (status >= 500) {
        return new StaffApiError("server", data?.message ?? "Ошибка сервера", status);
      }
      return new StaffApiError(
        "bad_response",
        data?.message ?? `Неожиданный ответ сервера (${status})`,
        status,
        data?.code,
      );
  }
}
