import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { useOnlineStatus } from "../hooks/useOnlineStatus";
import {
  readStaffSnapshot,
  removeStaffSnapshot,
  writeStaffSnapshot,
} from "../pwa/staffSnapshot";
import { fetchWorkLocations } from "../seller/bootstrap";
import type { WorkLocation } from "../seller/types";
import {
  completeOrder,
  escalateOrder,
  fetchIncomingTransfers,
  fetchOrder,
  fetchQueue,
  receiveTransfer,
  releaseOrder,
  // API-функция и Context-команда имеют одинаковый смысл, поэтому даём
  // импорту явное имя и не создаём конфликт идентификаторов ниже.
  reportShortage as reportShortageApi,
  takeOrder,
} from "./api";
import type {
  FulfillmentIssue,
  PickerOrder,
  PickerPaginationMeta,
  PickerQueueFilters,
} from "./types";

const WAREHOUSE_KEY = "picker_warehouse_id";
const SNAPSHOT_SCOPE = "picker";

/** Последний подтверждённый сервером снимок. Он нужен только для чтения offline. */
interface PickerSnapshot {
  workLocations: WorkLocation[];
  warehouseId: number;
  warehouseName: string;
  queue: PickerOrder[];
  myOrders: PickerOrder[];
  transfers: PickerOrder[];
  meta: PickerPaginationMeta | null;
}

/**
 * Общий контракт picker-раздела.
 *
 * «Очередь» и «Моя работа» намеренно разделены по backend-статусам:
 * queue = свободные confirmed, myOrders = мои активные processing. Завершённые
 * задания здесь не являются историей: текущий picker API их не возвращает.
 */
export interface PickerContextValue {
  workLocations: WorkLocation[];
  warehouseId: number | null;
  warehouseName: string | null;
  ready: boolean;
  loading: boolean;
  error: string | null;
  online: boolean;

  queue: PickerOrder[];
  myOrders: PickerOrder[];
  transfers: PickerOrder[];
  transfersLoading: boolean;
  transfersError: string | null;
  meta: PickerPaginationMeta | null;

  init(): Promise<void>;
  selectWarehouse(id: number): Promise<void>;
  refresh(): Promise<void>;
  refreshTransfers(): Promise<void>;
  loadOrder(orderId: number): Promise<PickerOrder>;
  take(orderId: number): Promise<PickerOrder>;
  release(orderId: number): Promise<PickerOrder>;
  complete(orderId: number): Promise<PickerOrder>;
  escalate(orderId: number, comment: string): Promise<PickerOrder>;
  reportShortage(
    orderId: number,
    payload: {
      product_id: number;
      shortage_quantity: number;
      comment?: string;
    },
  ): Promise<{ order: PickerOrder; fulfillment_issue: FulfillmentIssue }>;
  receive(orderId: number): Promise<PickerOrder>;
  resetWarehouse(): void;
}

export const PickerContext = createContext<PickerContextValue | null>(null);

export function PickerProvider({ children }: { children: ReactNode }) {
  // React-state живёт в памяти вкладки. Снимок из Web Storage только даёт
  // стартовые значения при перезагрузке PWA без сети.
  const cachedRef = useRef(readStaffSnapshot<PickerSnapshot>(SNAPSHOT_SCOPE));
  const cached = cachedRef.current;
  const online = useOnlineStatus();

  const [workLocations, setWorkLocations] = useState<WorkLocation[]>(
    () => cached?.workLocations ?? [],
  );
  const [warehouseId, setWarehouseId] = useState<number | null>(
    () => cached?.warehouseId ?? null,
  );
  const [warehouseName, setWarehouseName] = useState<string | null>(
    () => cached?.warehouseName ?? null,
  );
  const [ready, setReady] = useState(() => Boolean(cached));
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [queue, setQueue] = useState<PickerOrder[]>(() => cached?.queue ?? []);
  const [myOrders, setMyOrders] = useState<PickerOrder[]>(() => cached?.myOrders ?? []);
  const [transfers, setTransfers] = useState<PickerOrder[]>(
    () => cached?.transfers ?? [],
  );
  const [transfersLoading, setTransfersLoading] = useState(false);
  const [transfersError, setTransfersError] = useState<string | null>(null);
  const [meta, setMeta] = useState<PickerPaginationMeta | null>(
    () => cached?.meta ?? null,
  );
  const initPromiseRef = useRef<Promise<void> | null>(null);

  // Сохраняем только выбранную рабочую точку и серверные списки. Никакие
  // pending-команды в snapshot не попадают и офлайн не воспроизводятся.
  useEffect(() => {
    if (!ready || warehouseId === null || warehouseName === null) return;
    const snapshot: PickerSnapshot = {
      workLocations,
      warehouseId,
      warehouseName,
      queue,
      myOrders,
      transfers,
      meta,
    };
    cachedRef.current = snapshot;
    writeStaffSnapshot<PickerSnapshot>(SNAPSHOT_SCOPE, snapshot);
  }, [ready, workLocations, warehouseId, warehouseName, queue, myOrders, transfers, meta]);

  const applyError = useCallback((err: unknown): string => {
    const message = err instanceof Error ? err.message : "Неизвестная ошибка";
    setError(message);
    return message;
  }, []);

  const requireOnline = useCallback(() => {
    if (!online) throw new Error("Для изменения задания нужен интернет");
  }, [online]);

  /**
   * Сервер без фильтра отдаёт и свободные, и уже взятые текущим picker заказы.
   * Явные статусы устраняют визуальные дубли и объясняют жизненный цикл экрана.
   */
  const loadLists = useCallback(async (targetWarehouseId: number | null) => {
    if (targetWarehouseId === null) return;
    const [available, mine] = await Promise.all([
      fetchQueue({ warehouse_id: targetWarehouseId, status: "confirmed" }),
      fetchQueue({
        warehouse_id: targetWarehouseId,
        mine: true,
        status: "processing",
      }),
    ]);
    setQueue(available.data);
    setMyOrders(mine.data);
    setMeta(available.meta);
  }, []);

  /** Загружает входящие части, которые курьер уже привёз (`awaiting_receipt`). */
  const refreshTransfers = useCallback(async () => {
    if (!online) return;
    setTransfersLoading(true);
    setTransfersError(null);
    try {
      const response = await fetchIncomingTransfers();
      setTransfers(response.data);
    } catch (err) {
      setTransfersError(
        err instanceof Error ? err.message : "Не удалось загрузить трансферы",
      );
    } finally {
      setTransfersLoading(false);
    }
  }, [online]);

  /**
   * StrictMode может дважды вызвать effect Layout. Пока первый init работает,
   * второй получает тот же Promise и не создаёт дублирующие HTTP-запросы.
   */
  const init = useCallback(async () => {
    if (initPromiseRef.current) return initPromiseRef.current;

    const task = (async () => {
      setLoading(true);
      setError(null);
      try {
        const locations = await fetchWorkLocations();
        setWorkLocations(locations);

        const saved = Number(localStorage.getItem(WAREHOUSE_KEY));
        const location = locations.find((item) => item.id === saved) ?? null;
        if (!location) {
          localStorage.removeItem(WAREHOUSE_KEY);
          removeStaffSnapshot(SNAPSHOT_SCOPE);
          setWarehouseId(null);
          setWarehouseName(null);
          setQueue([]);
          setMyOrders([]);
          setTransfers([]);
          setMeta(null);
          setReady(false);
          return;
        }

        setWarehouseId(location.id);
        setWarehouseName(location.name);
        await loadLists(location.id);
        setReady(true);
      } catch (err) {
        if (cachedRef.current) {
          // Холодный offline-start: оставляем последний серверный снимок видимым.
          setError("Нет связи с сервером — показаны сохранённые данные");
          setReady(true);
        } else {
          applyError(err);
        }
      } finally {
        setLoading(false);
      }
    })();

    initPromiseRef.current = task;
    try {
      await task;
    } finally {
      if (initPromiseRef.current === task) initPromiseRef.current = null;
    }
  // `online` в зависимостях нужен намеренно: если PWA открыли без снимка и
  // сеть вернулась, Layout повторит init и покажет экран выбора/очередь.
  }, [applyError, loadLists, online]);

  const selectWarehouse = useCallback(
    async (id: number) => {
      requireOnline();
      setLoading(true);
      setError(null);
      try {
        const location = workLocations.find((item) => item.id === id);
        if (!location) throw new Error("Точка не найдена");
        localStorage.setItem(WAREHOUSE_KEY, String(id));
        setWarehouseId(id);
        setWarehouseName(location.name);
        setTransfers([]);
        await loadLists(location.id);
        setReady(true);
      } catch (err) {
        applyError(err);
        throw err;
      } finally {
        setLoading(false);
      }
    },
    [workLocations, loadLists, applyError, requireOnline],
  );

  const refresh = useCallback(async () => {
    if (!online) return;
    setError(null);
    try {
      await loadLists(warehouseId);
    } catch (err) {
      applyError(err);
    }
  }, [online, warehouseId, loadLists, applyError]);

  // POST уже мог успешно изменить заказ. Если последующий GET списков упал,
  // не выдаём команду за неуспешную: сохраняем ошибку обновления и возвращаем
  // странице авторитетный объект из ответа POST.
  const reconcileLists = useCallback(async () => {
    try {
      await loadLists(warehouseId);
    } catch (err) {
      applyError(err);
    }
  }, [warehouseId, loadLists, applyError]);

  const resetWarehouse = useCallback(() => {
    localStorage.removeItem(WAREHOUSE_KEY);
    removeStaffSnapshot(SNAPSHOT_SCOPE);
    cachedRef.current = null;
    setWarehouseId(null);
    setWarehouseName(null);
    setQueue([]);
    setMyOrders([]);
    setTransfers([]);
    setMeta(null);
    setReady(false);
    setError(null);
  }, []);

  /** Заменяет уже известный заказ, не создавая копий с одинаковым id. */
  const upsertInLists = useCallback((order: PickerOrder) => {
    const replaceKnown = (previous: PickerOrder[]) => {
      const index = previous.findIndex((item) => item.id === order.id);
      if (index === -1) return previous;
      const next = [...previous];
      next[index] = order;
      return next;
    };
    setQueue(replaceKnown);
    setMyOrders(replaceKnown);
    setTransfers(replaceKnown);
  }, []);

  const loadOrder = useCallback(
    async (orderId: number): Promise<PickerOrder> => {
      const known =
        queue.find((item) => item.id === orderId) ??
        myOrders.find((item) => item.id === orderId) ??
        transfers.find((item) => item.id === orderId);
      if (known) return known;
      requireOnline();
      const order = await fetchOrder(orderId);
      upsertInLists(order);
      return order;
    },
    [queue, myOrders, transfers, requireOnline, upsertInLists],
  );

  // POST-команды не оптимистичны: ждём объект backend, затем сверяем списки.
  const take = useCallback(
    async (orderId: number) => {
      requireOnline();
      const order = await takeOrder(orderId);
      upsertInLists(order);
      await reconcileLists();
      return order;
    },
    [requireOnline, upsertInLists, reconcileLists],
  );

  const release = useCallback(
    async (orderId: number) => {
      requireOnline();
      const order = await releaseOrder(orderId);
      upsertInLists(order);
      await reconcileLists();
      return order;
    },
    [requireOnline, upsertInLists, reconcileLists],
  );

  const complete = useCallback(
    async (orderId: number) => {
      requireOnline();
      const order = await completeOrder(orderId);
      upsertInLists(order);
      await reconcileLists();
      return order;
    },
    [requireOnline, upsertInLists, reconcileLists],
  );

  const escalate = useCallback(
    async (orderId: number, comment: string) => {
      requireOnline();
      const order = await escalateOrder(orderId, comment);
      upsertInLists(order);
      await reconcileLists();
      return order;
    },
    [requireOnline, upsertInLists, reconcileLists],
  );

  const reportShortage = useCallback(
    async (
      orderId: number,
      payload: {
        product_id: number;
        shortage_quantity: number;
        comment?: string;
      },
    ) => {
      requireOnline();
      const result = await reportShortageApi(orderId, payload);
      upsertInLists(result.order);
      await reconcileLists();
      return result;
    },
    [requireOnline, upsertInLists, reconcileLists],
  );

  const receive = useCallback(
    async (orderId: number) => {
      requireOnline();
      const order = await receiveTransfer(orderId);
      upsertInLists(order);
      // После приёмки transfer исчезает, а consolidation может появиться в queue.
      await Promise.all([reconcileLists(), refreshTransfers()]);
      return order;
    },
    [requireOnline, upsertInLists, reconcileLists, refreshTransfers],
  );

  const value = useMemo<PickerContextValue>(
    () => ({
      workLocations,
      warehouseId,
      warehouseName,
      ready,
      loading,
      error,
      online,
      queue,
      myOrders,
      transfers,
      transfersLoading,
      transfersError,
      meta,
      init,
      selectWarehouse,
      refresh,
      refreshTransfers,
      loadOrder,
      take,
      release,
      complete,
      escalate,
      reportShortage,
      receive,
      resetWarehouse,
    }),
    [
      workLocations,
      warehouseId,
      warehouseName,
      ready,
      loading,
      error,
      online,
      queue,
      myOrders,
      transfers,
      transfersLoading,
      transfersError,
      meta,
      init,
      selectWarehouse,
      refresh,
      refreshTransfers,
      loadOrder,
      take,
      release,
      complete,
      escalate,
      reportShortage,
      receive,
      resetWarehouse,
    ],
  );

  return <PickerContext.Provider value={value}>{children}</PickerContext.Provider>;
}

/** Понятная точка входа в Context для picker-компонентов. */
export function usePicker(): PickerContextValue {
  const context = useContext(PickerContext);
  if (!context) throw new Error("usePicker must be used within PickerProvider");
  return context;
}

export type { PickerQueueFilters };
