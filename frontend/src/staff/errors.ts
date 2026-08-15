// Импортируем axios и тип AxiosError для работы с HTTP-ответами.
import axios, { type AxiosError } from "axios";

// Категории ошибок: по ним интерфейс решает, что показать пользователю.
export type StaffErrorKind =
  | "offline" // нет соединения с сервером
  | "timeout" // сервер не ответил вовремя
  | "unauthorized" // сессия истекла (401)
  | "forbidden" // недостаточно прав (403)
  | "not_found" // сущность не найдена (404)
  | "conflict" // конфликт состояния (409)
  | "validation" // ошибки валидации полей (422)
  | "throttled" // слишком много запросов (429)
  | "server" // ошибка сервера (5xx)
  | "bad_response" // неожиданный ответ (400 / прочее)
  | "unknown"; // всё остальное

// Структура тела ошибки, которую отдаёт Laravel.
interface ErrorBody {
  message?: string; // текст ошибки
  code?: string; // машинный код (например, для конфликтов workflow)
  errors?: Record<string, string[]>; // ошибки по полям (для 422)
}

/**
 * Общая ошибка всех staff-разделов. `kind` нужен интерфейсу, `status` — для
 * диагностики, `code` — для конкретного конфликта workflow, а `fields` — для
 * подсветки полей после Laravel 422.
 */
export class StaffApiError extends Error {
  constructor(
    public kind: StaffErrorKind, // категория ошибки
    message: string, // человекочитаемое сообщение
    public status?: number, // HTTP-статус
    public code?: string, // машинный код ошибки
    public fields?: Record<string, string[]>, // ошибки по полям формы
    public retryAfterMs?: number, // когда можно повторить запрос (для 429)
  ) {
    super(message); // передаём сообщение в базовый Error
    this.name = "StaffApiError"; // имя класса для диагностики
  }

  // Можно ли безопасно автоматически повторить запрос позже.
  get retryable(): boolean {
    return ["offline", "timeout", "throttled", "server"].includes(this.kind);
  }
}

// Читает значение конкретного заголовка ответа (или undefined).
function headerValue(error: AxiosError<ErrorBody>, name: string): string | undefined {
  const value = error.response?.headers[name]; // берём заголовок из ответа
  return typeof value === "string" || typeof value === "number" ? String(value) : undefined;
}

/** Retry-After бывает числом секунд или HTTP-датой. Laravel также отдаёт
 * X-RateLimit-Reset как Unix timestamp, поэтому используем его как fallback. */
function readRetryAfterMs(error: AxiosError<ErrorBody>): number | undefined {
  const retryAfter = headerValue(error, "retry-after"); // заголовок Retry-After
  if (retryAfter) {
    const seconds = Number(retryAfter); // пробуем прочитать как секунды
    if (Number.isFinite(seconds) && seconds >= 0) return seconds * 1000; // переводим в мс

    const retryDate = Date.parse(retryAfter); // пробуем прочитать как дату
    if (Number.isFinite(retryDate)) return Math.max(0, retryDate - Date.now()); // до этой даты
  }

  const reset = Number(headerValue(error, "x-ratelimit-reset")); // fallback-заголовок
  // Если proxy вырезал оба заголовка, всё равно не разрешаем немедленно
  // заспамить сервер повтором: используем консервативные 30 секунд.
  return Number.isFinite(reset) ? Math.max(0, reset * 1000 - Date.now()) : 30_000;
}

// Главная функция: приводит любое исключение к StaffApiError с понятным сообщением.
export function toStaffApiError(error: unknown, entity = "Запись"): StaffApiError {
  if (error instanceof StaffApiError) return error; // уже наша ошибка — возвращаем как есть
  if (!axios.isAxiosError(error)) {
    // Не Axios-ошибка: JS-исключение или неизвестное значение.
    return new StaffApiError(
      "unknown", // категория — неизвестная
      error instanceof Error ? error.message : "Неизвестная ошибка", // берём текст ошибки
    );
  }

  const axiosError = error as AxiosError<ErrorBody>; // уточняем тип
  if (axiosError.code === "ERR_CANCELED") {
    return new StaffApiError("unknown", "Запрос отменён"); // отменённый запрос
  }
  if (axiosError.code === "ECONNABORTED" || axiosError.code === "ETIMEDOUT") {
    return new StaffApiError("timeout", "Сервер не ответил вовремя"); // таймаут
  }
  if (!axiosError.response) {
    return new StaffApiError("offline", "Нет связи с сервером"); // ответа не было вовсе
  }

  const { status, data } = axiosError.response; // статус и тело ответа
  switch (status) {
    case 400:
      return new StaffApiError("bad_response", data?.message ?? "Некорректный запрос", status, data?.code);
    case 401:
      return new StaffApiError("unauthorized", "Сессия истекла, войдите заново", status, data?.code);
    case 403:
      return new StaffApiError("forbidden", data?.message ?? "Недостаточно прав", status, data?.code);
    case 404:
      return new StaffApiError("not_found", data?.message ?? `${entity} не найдена`, status, data?.code);
    case 409:
      return new StaffApiError(
        "conflict",
        data?.message ?? "Состояние изменилось. Обновите данные", // конфликт состояния
        status,
        data?.code,
      );
    case 422:
      return new StaffApiError(
        "validation",
        data?.message ?? "Проверьте введённые данные",
        status,
        data?.code,
        data?.errors, // сохраняем ошибки по полям для подсветки
      );
    case 429:
      return new StaffApiError(
        "throttled",
        data?.message ?? "Слишком много запросов. Подождите перед повтором",
        status,
        data?.code,
        undefined,
        readRetryAfterMs(axiosError), // время до повтора
      );
    default:
      if (status >= 500) {
        return new StaffApiError("server", data?.message ?? "Ошибка сервера", status, data?.code);
      }
      return new StaffApiError(
        "bad_response",
        data?.message ?? `Неожиданный ответ сервера (${status})`, // прочие статусы
        status,
        data?.code,
      );
  }
}

// Возвращает первую ошибку по конкретному полю формы (или undefined).
export function fieldError(error: StaffApiError | null, field: string): string | undefined {
  return error?.kind === "validation" ? error.fields?.[field]?.[0] : undefined;
}

// Сколько секунд осталось до повтора (минимум 1), для вывода таймера.
export function retrySeconds(error: StaffApiError | null): number {
  return Math.max(1, Math.ceil((error?.retryAfterMs ?? 0) / 1000));
}
