/**
 * Laravel JsonResource обычно заворачивает полезные данные в `{ data: ... }`,
 * но часть старых контроллеров проекта возвращает объект напрямую. Эта функция
 * позволяет API-слою одинаково читать оба варианта, не размазывая проверки по UI.
 */
export function unwrapData<T>(payload: T | { data: T }): T {
  if (payload && typeof payload === "object" && "data" in payload) {
    return (payload as { data: T }).data;
  }
  return payload as T;
}
