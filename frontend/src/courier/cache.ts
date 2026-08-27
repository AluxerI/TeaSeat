import { readStaffSnapshot, writeStaffSnapshot } from "../pwa/staffSnapshot";
import { createCourierPwaState } from "./reducer";
import type {
  CourierDelivery,
  CourierListKey,
  CourierPwaState,
  PaginationMeta,
} from "./types";

const SNAPSHOT_SCOPE = "courier";

interface CachedCourierList {
  ids: number[];
  meta: PaginationMeta | null;
  lastFetchedAt: number | null;
}
interface CourierSnapshot {
  deliveries: Record<number, CourierDelivery>;
  lists: Record<CourierListKey, CachedCourierList>;
  lastSyncAt: number | null;
}

/** Восстанавливает только данные; loading, ошибки, Snackbar и POST-команды не кешируются. */
export function restoreCourierState(online: boolean): CourierPwaState {
  const snapshot = readStaffSnapshot<CourierSnapshot>(SNAPSHOT_SCOPE);
  const fresh = createCourierPwaState(online);
  if (!snapshot) return fresh;

  for (const key of Object.keys(fresh.lists) as CourierListKey[]) {
    const cachedList = snapshot.lists[key];
    fresh.lists[key] = {
      ids: cachedList?.ids ?? [],
      meta: cachedList?.meta ?? null,
      lastFetchedAt: cachedList?.lastFetchedAt ?? null,
      phase: cachedList?.lastFetchedAt ? "success" : "idle",
      error: null,
    };
  }
  fresh.deliveries = snapshot.deliveries ?? {};
  fresh.lastSyncAt = snapshot.lastSyncAt ?? null;
  return fresh;
}

/** Сохраняет последний успешный GET-снимок для просмотра при offline-start. */
export function persistCourierState(state: CourierPwaState): void {
  if (state.lastSyncAt === null) return;
  writeStaffSnapshot<CourierSnapshot>(SNAPSHOT_SCOPE, {
    deliveries: state.deliveries,
    lists: {
      queue: pickList(state, "queue"),
      mine: pickList(state, "mine"),
      history: pickList(state, "history"),
    },
    lastSyncAt: state.lastSyncAt,
  });
}

function pickList(state: CourierPwaState, key: CourierListKey): CachedCourierList {
  const list = state.lists[key];
  return {
    ids: list.ids,
    meta: list.meta,
    lastFetchedAt: list.lastFetchedAt,
  };
}
