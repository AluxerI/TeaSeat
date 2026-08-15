import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { useLiveQuery } from "dexie-react-hooks";
import {
  db,
  readSession,
  resetSellerDatabase,
  toStorageErrorMessage,
} from "../seller/db";
import {
  bootstrapWarehouse,
  cachedProducts,
  fetchWorkLocations,
} from "../seller/bootstrap";
import {
  commitOrder,
  createOrResumeDraft,
  addProductToDraft,
  deleteDraft,
  enqueueCommand,
  enqueueDayClosing,
  getOrder,
  reopenForEdit,
  removeItem as removeItemFromItems,
  saveDraft,
  upsertItem,
} from "../seller/orders";
import { drainOnce, type DrainSummary } from "../seller/sync";
import { normalizeQuantity, previewOrderTotal } from "../seller/quantity";
import type {
  LocalOrder,
  LocalProduct,
  OutboxAction,
  PaymentMethod,
  SellerSession,
  WorkLocation,
} from "../seller/types";

export interface SellerContextValue {
  // ── Сессия и рабочая точка ─────────────────────────────────────────────
  session: SellerSession | null;
  workLocations: WorkLocation[];
  products: LocalProduct[];
  warehouseId: number | null;
  ready: boolean;
  loading: boolean;
  error: string | null;
  online: boolean;
  snapshotExpired: boolean;

  init(): Promise<void>;
  selectWarehouse(id: number): Promise<void>;
  refreshCatalog(): Promise<void>;
  resetSession(): Promise<void>;

  // ── Визард оформления ───────────────────────────────────────────────────
  draft: LocalOrder | null;
  startDraft(): Promise<LocalOrder>;
  openDraft(clientOrderId: string): Promise<void>;
  closeDraft(): void;
  updateDraft(
    patch: Partial<Pick<LocalOrder, "payment_method" | "customer_note">>
  ): Promise<void>;
  addItem(product: LocalProduct, quantity?: number): Promise<void>;
  updateItemQuantity(productId: number, quantity: number): Promise<void>;
  removeItem(productId: number): Promise<void>;
  clearItems(): Promise<void>;
  commitDraft(): Promise<void>;

  // ── Заказы и синхронизация ─────────────────────────────────────────────
  orders: LocalOrder[];
  pendingCount: number;
  syncing: Set<string>;
  syncOrder(clientOrderId: string): Promise<DrainSummary>;
  syncAll(): Promise<DrainSummary>;
  enqueue(
    clientOrderId: string,
    action: Exclude<OutboxAction, "upsert">
  ): Promise<void>;
  completeDay(): Promise<number>;
  deleteOrder(clientOrderId: string): Promise<void>;
  reopen(clientOrderId: string): Promise<LocalOrder>;
}

export const SellerContext = createContext<SellerContextValue | null>(null);

export function SellerProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<SellerSession | null>(null);
  const [workLocations, setWorkLocations] = useState<WorkLocation[]>([]);
  const [products, setProducts] = useState<LocalProduct[]>([]);
  const [warehouseId, setWarehouseId] = useState<number | null>(null);
  const [ready, setReady] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [online, setOnline] = useState<boolean>(navigator.onLine);
  const [draft, setDraft] = useState<LocalOrder | null>(null);
  const [syncing, setSyncing] = useState<Set<string>>(new Set());

  // Заказы и очередь — реактивно из IndexedDB.
  const orders =
    useLiveQuery(
      () => db.orders.orderBy("created_at").reverse().toArray(),
      []
    ) ?? [];
  const pendingCount =
    useLiveQuery(
      () => db.outbox.where("state").equals("pending").count(),
      []
    ) ?? 0;

  useEffect(() => {
    const on = () => setOnline(true);
    const off = () => setOnline(false);
    window.addEventListener("online", on);
    window.addEventListener("offline", off);
    return () => {
      window.removeEventListener("online", on);
      window.removeEventListener("offline", off);
    };
  }, []);

  const applyError = useCallback((err: unknown): string => {
    // Ошибки IndexedDB/Dexie (DataError, UpgradeError и т.п.) показываем
    // русским текстом — сырые `DataError: Data provided to...` непонятны
    // продавцу на кассе. Остальное — как есть или общая фраза.
    const storageMessage = toStorageErrorMessage(err);
    const message =
      storageMessage ??
      (err instanceof Error ? err.message : "Неизвестная ошибка");
    setError(message);
    return message;
  }, []);

  // ── Инициализация ───────────────────────────────────────────────────────

  const init = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const s = await readSession();
      setSession(s);

      if (s.warehouse_id !== null) {
        setWarehouseId(s.warehouse_id);
        setProducts(await cachedProducts(s.warehouse_id));
        setReady(true);
        // Фоном обновляем каталог, если есть сеть; при неудаче остаёмся на кэше.
        try {
          const fresh = await bootstrapWarehouse(
            s.warehouse_id,
            s.device_name || "Касса"
          );
          setProducts(fresh);
          setSession(await readSession());
        } catch {
          /* offline — кэш достаточно свежий */
        }
      } else {
        setWorkLocations(await fetchWorkLocations());
      }
    } catch (err) {
      applyError(err);
    } finally {
      setLoading(false);
    }
  }, [applyError]);

  const selectWarehouse = useCallback(
    async (id: number) => {
      setLoading(true);
      setError(null);
      try {
        const name = session?.device_name || "Касса";
        const fresh = await bootstrapWarehouse(id, name);
        setProducts(fresh);
        setWarehouseId(id);
        setReady(true);
        setSession(await readSession());
      } catch (err) {
        applyError(err);
        throw err;
      } finally {
        setLoading(false);
      }
    },
    [session, applyError]
  );

  const refreshCatalog = useCallback(async () => {
    if (!warehouseId) return;
    setLoading(true);
    setError(null);
    try {
      const s = await readSession();
      const fresh = await bootstrapWarehouse(
        warehouseId,
        s.device_name || "Касса"
      );
      setProducts(fresh);
      setSession(await readSession());
    } catch (err) {
      applyError(err);
    } finally {
      setLoading(false);
    }
  }, [warehouseId, applyError]);

  const resetSession = useCallback(async () => {
    await resetSellerDatabase();
    setSession(null);
    setWarehouseId(null);
    setProducts([]);
    setReady(false);
    setDraft(null);
    setError(null);
  }, []);

  const snapshotExpired = useMemo(() => {
    if (!session?.snapshot_expires_at) return false;
    return Date.now() > Date.parse(session.snapshot_expires_at);
  }, [session]);

  // ── Визард ──────────────────────────────────────────────────────────────

  const startDraft = useCallback(async (): Promise<LocalOrder> => {
    if (warehouseId === null) throw new Error("Не выбрана рабочая точка");
    if (snapshotExpired)
      throw new Error("Снимок цен устарел — обновите каталог");
    const d = await createOrResumeDraft(warehouseId);
    setDraft(d);
    return d;
  }, [warehouseId, snapshotExpired]);

  const openDraft = useCallback(async (clientOrderId: string) => {
    const d = await getOrder(clientOrderId);
    setDraft(d ?? null);
  }, []);

  const closeDraft = useCallback(() => setDraft(null), []);

  const updateDraft = useCallback(
    async (patch: Partial<Pick<LocalOrder, "payment_method" | "customer_note">>) => {
      if (!draft) return;
      const next = await saveDraft(draft.client_order_id, patch);
      setDraft(next);
    },
    [draft]
  );

  const addItem = useCallback(
    async (product: LocalProduct, quantity?: number) => {
      const base = draft ?? (await startDraft());
      const next = await addProductToDraft(
        base.client_order_id,
        product,
        quantity ?? product.sale_step
      );
      setDraft(next);
    },
    [draft, startDraft]
  );

  const updateItemQuantity = useCallback(
    async (productId: number, quantity: number) => {
      if (!draft) return;
      const item = draft.items.find((i) => i.product_id === productId);
      if (!item) return;
      let items = draft.items;
      if (quantity <= 0) {
        items = removeItemFromItems(draft.items, productId);
      } else {
        items = upsertItem(draft.items, {
          ...item,
          quantity: normalizeQuantity(quantity, item.sale_step),
        });
      }
      const next = await saveDraft(draft.client_order_id, { items });
      setDraft(next);
    },
    [draft]
  );

  const removeItem = useCallback(
    async (productId: number) => {
      if (!draft) return;
      const next = await saveDraft(draft.client_order_id, {
        items: removeItemFromItems(draft.items, productId),
      });
      setDraft(next);
    },
    [draft]
  );

  const clearItems = useCallback(async () => {
    if (!draft) return;
    const next = await saveDraft(draft.client_order_id, { items: [] });
    setDraft(next);
  }, [draft]);

  const commitDraft = useCallback(async () => {
    if (!draft) return;
    const committed = await commitOrder(draft.client_order_id);
    setDraft(null);
    // Если есть сеть — сразу уводим заказ на сервер.
    if (navigator.onLine) {
      try {
        await drainOnce(committed.client_order_id);
      } catch {
        /* остаётся в очереди, синхронизируем вручную */
      }
    }
  }, [draft]);

  // ── Заказы и синхронизация ──────────────────────────────────────────────

  const syncOrder = useCallback(
    async (clientOrderId: string): Promise<DrainSummary> => {
      setSyncing((prev) => new Set(prev).add(clientOrderId));
      try {
        return await drainOnce(clientOrderId);
      } finally {
        setSyncing((prev) => {
          const next = new Set(prev);
          next.delete(clientOrderId);
          return next;
        });
      }
    },
    []
  );

  const syncAll = useCallback(async (): Promise<DrainSummary> => {
    return drainOnce();
  }, []);

  const enqueue = useCallback(
    async (clientOrderId: string, action: Exclude<OutboxAction, "upsert">) => {
      await enqueueCommand(clientOrderId, action);
      if (navigator.onLine) {
        try {
          await drainOnce(clientOrderId);
        } catch {
          /* остаётся в очереди */
        }
      }
    },
    []
  );

  const completeDay = useCallback(async (): Promise<number> => {
    const count = await enqueueDayClosing();
    if (count > 0 && navigator.onLine) {
      try {
        await drainOnce();
      } catch {
        /* остаётся в очереди */
      }
    }
    return count;
  }, []);

  const deleteOrder = useCallback(async (clientOrderId: string) => {
    await deleteDraft(clientOrderId);
  }, []);

  const reopen = useCallback(
    async (clientOrderId: string): Promise<LocalOrder> => {
      const order = await reopenForEdit(clientOrderId);
      setDraft(order);
      return order;
    },
    []
  );

  const value: SellerContextValue = {
    session,
    workLocations,
    products,
    warehouseId,
    ready,
    loading,
    error,
    online,
    snapshotExpired,
    init,
    selectWarehouse,
    refreshCatalog,
    resetSession,
    draft,
    startDraft,
    openDraft,
    closeDraft,
    updateDraft,
    addItem,
    updateItemQuantity,
    removeItem,
    clearItems,
    commitDraft,
    orders,
    pendingCount,
    syncing,
    syncOrder,
    syncAll,
    enqueue,
    completeDay,
    deleteOrder,
    reopen,
  };

  return (
    <SellerContext.Provider value={value}>{children}</SellerContext.Provider>
  );
}

export function useSeller(): SellerContextValue {
  const ctx = useContext(SellerContext);
  if (!ctx) throw new Error("useSeller must be used within SellerProvider");
  return ctx;
}
