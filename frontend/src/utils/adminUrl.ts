function withoutTrailingSlash(value: string): string {
  return value.replace(/\/+$/, "");
}

export function isLocalHostname(hostname: string): boolean {
  return hostname === "localhost" || hostname === "127.0.0.1" || hostname === "::1";
}

export function getAdminPanelUrl(): string {
  const explicit = import.meta.env.VITE_ADMIN_URL?.trim();
  if (explicit) return explicit;

  const apiBase = import.meta.env.VITE_API_URL?.trim();
  if (apiBase) {
    const url = new URL(apiBase, window.location.origin);
    url.pathname = url.pathname.replace(/\/api\/?$/, "");
    return `${withoutTrailingSlash(url.toString())}/admin`;
  }

  if (import.meta.env.DEV && isLocalHostname(window.location.hostname)) {
    return `${window.location.protocol}//${window.location.hostname}:8000/admin`;
  }

  return new URL("/admin", window.location.origin).toString();
}
