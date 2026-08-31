/**
 * Небольшой слой над Web Storage (часть Browser Object Model).
 *
 * React Context держит актуальные данные только пока открыта вкладка. Этот
 * модуль сохраняет последний подтверждённый сервером снимок, чтобы после
 * перезагрузки без сети picker/courier увидели уже загруженные задания.
 * Команды офлайн не выполняются: backend остаётся единственным источником
 * истины для смены статусов и складских движений.
 */

const AUTH_USER_CACHE_KEY = "auth_user_cache";
const STORAGE_PREFIX = "teaseat_staff_pwa:v1:";

interface StoredSnapshot<T> {
  version: 1;
  userId: number;
  savedAt: number;
  data: T;
}

function currentUserId(): number | null {
  try {
    const raw = localStorage.getItem(AUTH_USER_CACHE_KEY);
    if (!raw) return null;
    const id = Number((JSON.parse(raw) as { id?: unknown }).id);
    return Number.isInteger(id) && id > 0 ? id : null;
  } catch {
    return null;
  }
}

function storageKey(scope: string, userId: number): string {
  return `${STORAGE_PREFIX}${scope}:user:${userId}`;
}

/** Читает снимок только текущего авторизованного пользователя. */
export function readStaffSnapshot<T>(scope: string): T | null {
  const userId = currentUserId();
  if (userId === null) return null;

  try {
    const raw = localStorage.getItem(storageKey(scope, userId));
    if (!raw) return null;
    const snapshot = JSON.parse(raw) as StoredSnapshot<T>;
    if (snapshot.version !== 1 || snapshot.userId !== userId) return null;
    return snapshot.data;
  } catch {
    // Повреждённый/устаревший JSON не должен ломать запуск PWA.
    return null;
  }
}

/** Сохраняет только данные, уже полученные от backend. */
export function writeStaffSnapshot<T>(scope: string, data: T): void {
  const userId = currentUserId();
  if (userId === null) return;

  const snapshot: StoredSnapshot<T> = {
    version: 1,
    userId,
    savedAt: Date.now(),
    data,
  };

  try {
    localStorage.setItem(storageKey(scope, userId), JSON.stringify(snapshot));
  } catch {
    // QuotaExceeded/private mode: интерфейс продолжает работать online-first.
  }
}

/** Удаляет снимок одного раздела текущего пользователя. */
export function removeStaffSnapshot(scope: string): void {
  const userId = currentUserId();
  if (userId !== null) localStorage.removeItem(storageKey(scope, userId));
}

/** Вызывается при logout/смене аккаунта, чтобы сотрудники не увидели чужие данные. */
export function clearStaffSnapshots(): void {
  const keys: string[] = [];
  for (let index = 0; index < localStorage.length; index += 1) {
    const key = localStorage.key(index);
    if (key?.startsWith(STORAGE_PREFIX)) keys.push(key);
  }
  keys.forEach((key) => localStorage.removeItem(key));
}
