import { useCallback, useEffect, useSyncExternalStore } from "react";
import { wishlistApi, type WishlistEntry } from "../api/wishlistAPI";
import { extractError, translateError } from "../utils/translateError";

interface WishlistSnapshot {
  userId: number | null;
  entries: WishlistEntry[];
  productIds: ReadonlySet<number>;
  pendingProductIds: ReadonlySet<number>;
  loading: boolean;
  loaded: boolean;
  error: string;
}

let snapshot: WishlistSnapshot = {
  userId: null,
  entries: [],
  productIds: new Set<number>(),
  pendingProductIds: new Set<number>(),
  loading: false,
  loaded: false,
  error: "",
};

const listeners = new Set<() => void>();
let requestGeneration = 0;

function notify(next: WishlistSnapshot) {
  snapshot = next;
  listeners.forEach((listener) => listener());
}

function subscribe(listener: () => void) {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

function getSnapshot() {
  return snapshot;
}

function entryProductId(entry: WishlistEntry): number | null {
  const id = entry.product?.id ?? entry.product_id;
  return Number.isFinite(id) ? Number(id) : null;
}

function resetForUser(userId: number | null) {
  requestGeneration += 1;
  notify({
    userId,
    entries: [],
    productIds: new Set<number>(),
    pendingProductIds: new Set<number>(),
    loading: false,
    loaded: userId === null,
    error: "",
  });
}

async function load(userId: number, force = false) {
  if (snapshot.userId !== userId) resetForUser(userId);
  if (!force && (snapshot.loading || snapshot.loaded)) return;

  const generation = ++requestGeneration;
  notify({ ...snapshot, loading: true, error: "" });
  try {
    const entries = await wishlistApi.list();
    if (generation !== requestGeneration || snapshot.userId !== userId) return;
    const productIds = new Set<number>();
    entries.forEach((entry) => {
      const id = entryProductId(entry);
      if (id !== null) productIds.add(id);
    });
    notify({ ...snapshot, entries, productIds, loading: false, loaded: true, error: "" });
  } catch (error) {
    if (generation !== requestGeneration || snapshot.userId !== userId) return;
    notify({
      ...snapshot,
      loading: false,
      loaded: true,
      error: translateError(extractError(error)),
    });
  }
}

async function toggle(userId: number, productId: number): Promise<boolean> {
  if (snapshot.userId !== userId) resetForUser(userId);
  if (snapshot.pendingProductIds.has(productId)) return snapshot.productIds.has(productId);

  const wasFavorite = snapshot.productIds.has(productId);
  const pendingProductIds = new Set(snapshot.pendingProductIds);
  pendingProductIds.add(productId);
  notify({ ...snapshot, pendingProductIds, error: "" });

  try {
    if (wasFavorite) await wishlistApi.remove(productId);
    else await wishlistApi.add(productId);

    const productIds = new Set(snapshot.productIds);
    if (wasFavorite) productIds.delete(productId);
    else productIds.add(productId);

    const entries = wasFavorite
      ? snapshot.entries.filter((entry) => entryProductId(entry) !== productId)
      : snapshot.entries;

    const nextPending = new Set(snapshot.pendingProductIds);
    nextPending.delete(productId);
    notify({ ...snapshot, entries, productIds, pendingProductIds: nextPending, error: "" });

    // После добавления нужен серверный resource, чтобы профиль сразу получил
    // полную карточку товара. Повторный GET один на действие, а не один на карточку.
    if (!wasFavorite) await load(userId, true);
    return !wasFavorite;
  } catch (error) {
    const nextPending = new Set(snapshot.pendingProductIds);
    nextPending.delete(productId);
    notify({
      ...snapshot,
      pendingProductIds: nextPending,
      error: translateError(extractError(error)),
    });
    throw error;
  }
}

export function useWishlist(userId: number | null) {
  const state = useSyncExternalStore(subscribe, getSnapshot, getSnapshot);

  useEffect(() => {
    if (userId === null) {
      if (snapshot.userId !== null) resetForUser(null);
      return;
    }
    if (snapshot.userId !== userId) resetForUser(userId);
    void load(userId);
  }, [userId]);

  const refresh = useCallback(() => {
    if (userId !== null) return load(userId, true);
    return Promise.resolve();
  }, [userId]);

  const toggleProduct = useCallback(
    (productId: number) => {
      if (userId === null) return Promise.reject(new Error("Требуется авторизация"));
      return toggle(userId, productId);
    },
    [userId],
  );

  return {
    ...state,
    count: state.productIds.size,
    has: (productId: number) => state.productIds.has(productId),
    isPending: (productId: number) => state.pendingProductIds.has(productId),
    refresh,
    toggle: toggleProduct,
  };
}
