/**
 * В локальной разработке Laravel может вернуть абсолютный URL со своим host.
 * Оставляем только путь, чтобы запрос изображения прошёл через Vite proxy.
 */
export function normalizeAssetUrl(url?: string | null): string {
  return url?.replace(/^https?:\/\/[^/]+/, "") ?? "";
}
