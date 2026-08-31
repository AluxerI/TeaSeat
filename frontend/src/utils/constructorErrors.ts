export function constructorError(reason: unknown): string {
  const error = reason as { response?: { status?: number; data?: { message?: unknown } }; message?: string };
  const status = error?.response?.status;
  if (status === 401) return "Сессия истекла. Войдите в аккаунт заново.";
  if (status === 403) return "Нет доступа к конструктору для этого аккаунта.";
  if (status === 404) return "Коробка или API конструктора не найдены. Проверьте версию backend.";
  if (status === 429) return "Слишком много запросов. Подождите и повторите попытку.";
  if (status === 422 && typeof error.response?.data?.message === "string") return error.response.data.message;
  if (error?.message?.startsWith("Некорректный ответ")) return error.message;
  return "Не удалось получить ответ сервера. Проверьте соединение и повторите попытку.";
}

export function createGiftInstanceId(): string {
  if (typeof crypto.randomUUID === "function") return crypto.randomUUID();
  const bytes = crypto.getRandomValues(new Uint8Array(16));
  bytes[6] = (bytes[6] & 15) | 64;
  bytes[8] = (bytes[8] & 63) | 128;
  const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, "0")).join("");
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
