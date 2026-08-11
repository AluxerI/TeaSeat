import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
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
  // Переименовали в reportShortageApi, потому что ниже есть свой колбэк
  // с тем же именем reportShortage — иначе React Context не собирался.
  reportShortage as reportShortageApi,
  takeOrder,
} from "./api";
import type {
  FulfillmentIssue,
  PickerOrder,
  PickerPaginationMeta,
  PickerQueueFilters,
} from "./types";

// Ключ в localStorage, где запоминаем выбранный склад (чтобы не выбирать каждый раз)
const WAREHOUSE_KEY = "picker_warehouse_id";

/** Что может взять любая страница из этого контекста:
 *  данные (склад, очереди) и действия (взять/завершить/...).
 *  Это «общий ящик» для всех страниц секции /picker. */
export interface PickerContextValue {
  workLocations: WorkLocation[]; // склады, доступные этому пользователю
  warehouseId: number | null; // выбранный склад
  warehouseName: string | null;
  ready: boolean; // склад выбран и списки загружены — можно показывать интерфейс
  loading: boolean;
  error: string | null;
  online: boolean; // есть ли интернет (события online/offline)

  queue: PickerOrder[]; // вся очередь выбранного склада
  myOrders: PickerOrder[]; // только мои заказы
  meta: PickerPaginationMeta | null; // пагинация очереди

  init(): Promise<void>; // загрузка при старте приложения
  selectWarehouse(id: number): Promise<void>; // смена склада
  refresh(): Promise<void>; // перезагрузить очереди
  loadOrder(orderId: number): Promise<PickerOrder>; // заказ для детальной страницы
  take(orderId: number): Promise<void>; // взять в работу
  release(orderId: number): Promise<void>; // вернуть в очередь
  complete(orderId: number): Promise<void>; // завершить сборку
  escalate(orderId: number, comment: string): Promise<void>; // передать менеджеру
  reportShortage(
    orderId: number,
    payload: {
      product_id: number;
      shortage_quantity: number;
      comment?: string;
    }
  ): Promise<{ order: PickerOrder; fulfillment_issue: FulfillmentIssue }>; // недостача
  receive(orderId: number): Promise<void>; // принять трансфер
  resetWarehouse(): void; // сбросить выбор склада (выйти в экран выбора)
}

export const PickerContext = createContext<PickerContextValue | null>(null);

export function PickerProvider({ children }: { children: ReactNode }) {
  // --- Все состояния приложения живут здесь, в одном месте ---
  const [workLocations, setWorkLocations] = useState<WorkLocation[]>([]);
  const [warehouseId, setWarehouseId] = useState<number | null>(null);
  const [warehouseName, setWarehouseName] = useState<string | null>(null);
  const [ready, setReady] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [online, setOnline] = useState<boolean>(navigator.onLine);
  const [queue, setQueue] = useState<PickerOrder[]>([]);
  const [myOrders, setMyOrders] = useState<PickerOrder[]>([]);
  const [meta, setMeta] = useState<PickerPaginationMeta | null>(null);

  // Следим за интернетом: браузер шлёт события online/offline,
  // а мы просто показываем это в шапке чипом «Онлайн/Офлайн».
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

  // Записать ошибку в state, чтобы страницы могли её показать.
  // Возвращаем текст, чтобы вызвавший код тоже мог его использовать.
  const applyError = useCallback((err: unknown): string => {
    const message = err instanceof Error ? err.message : "Неизвестная ошибка";
    setError(message);
    return message;
  }, []);

  // Загрузить обе очереди (все заказы + мои) параллельно — так быстрее.
  const loadLists = useCallback(async () => {
    if (warehouseId === null) return;
    const [all, mine] = await Promise.all([
      fetchQueue({ warehouse_id: warehouseId }),
      fetchQueue({ warehouse_id: warehouseId, mine: true }),
    ]);
    setQueue(all.data);
    setMyOrders(mine.data);
    setMeta(all.meta);
  }, [warehouseId]);

  // Старт приложения: достаём доступные склады и пробуем восстановить
  // последний выбранный склад из localStorage.
  const init = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const locations = await fetchWorkLocations();
      setWorkLocations(locations);

      const saved = Number(localStorage.getItem(WAREHOUSE_KEY));
      const location = locations.find((l) => l.id === saved) ?? null;
      if (location) {
        setWarehouseId(location.id);
        setWarehouseName(location.name);
        await loadLists();
        setReady(true);
      }
    } catch (err) {
      applyError(err);
    } finally {
      setLoading(false);
    }
  }, [loadLists, applyError]);

  // Пользователь выбрал склад вручную — запоминаем его и грузим очереди.
  const selectWarehouse = useCallback(
    async (id: number) => {
      setLoading(true);
      setError(null);
      try {
        const location = workLocations.find((l) => l.id === id);
        if (!location) throw new Error("Точка не найдена");
        localStorage.setItem(WAREHOUSE_KEY, String(id));
        setWarehouseId(id);
        setWarehouseName(location.name);
        await loadLists();
        setReady(true);
      } catch (err) {
        applyError(err);
        throw err;
      } finally {
        setLoading(false);
      }
    },
    [workLocations, loadLists, applyError]
  );

  // Кнопка «Обновить» на странице очереди.
  const refresh = useCallback(async () => {
    setError(null);
    try {
      await loadLists();
    } catch (err) {
      applyError(err);
    }
  }, [loadLists, applyError]);

  // Выход из выбранного склада: чистим память и localStorage.
  const resetWarehouse = useCallback(() => {
    localStorage.removeItem(WAREHOUSE_KEY);
    setWarehouseId(null);
    setWarehouseName(null);
    setQueue([]);
    setMyOrders([]);
    setMeta(null);
    setReady(false);
    setError(null);
  }, []);

  // Обновить ОДИН заказ в уже загруженных списках (без полной перезагрузки).
  // Если заказа в списке нет — ничего не делаем (он мог сменить склад).
  const upsertInLists = useCallback((order: PickerOrder) => {
    setQueue((prev) => {
      const index = prev.findIndex((o) => o.id === order.id);
      if (index === -1) return prev;
      const next = [...prev];
      next[index] = order;
      return next;
    });
    setMyOrders((prev) => {
      const index = prev.findIndex((o) => o.id === order.id);
      if (index === -1) return prev;
      const next = [...prev];
      next[index] = order;
      return next;
    });
  }, []);

  // Для детальной страницы: сначала ищем заказ в уже загруженных списках
  // (мгновенно), и только если не нашли — идём на сервер.
  const loadOrder = useCallback(
    async (orderId: number): Promise<PickerOrder> => {
      const known =
        queue.find((o) => o.id === orderId) ??
        myOrders.find((o) => o.id === orderId);
      if (known) return known;
      const order = await fetchOrder(orderId);
      upsertInLists(order);
      return order;
    },
    [queue, myOrders, upsertInLists]
  );

  // Дальше — все действия сборщика. Паттерн у всех одинаковый:
  //   1) дёрнуть API (заказ на сервере меняет статус),
  //   2) обновить заказ в списках,
  //   3) перезагрузить очереди (чтобы заказ, например, ушёл из «моих»).
  const take = useCallback(
    async (orderId: number) => {
      const order = await takeOrder(orderId);
      upsertInLists(order);
      await loadLists();
    },
    [upsertInLists, loadLists]
  );

  const release = useCallback(
    async (orderId: number) => {
      const order = await releaseOrder(orderId);
      upsertInLists(order);
      await loadLists();
    },
    [upsertInLists, loadLists]
  );

  const complete = useCallback(
    async (orderId: number) => {
      const order = await completeOrder(orderId);
      upsertInLists(order);
      await loadLists();
    },
    [upsertInLists, loadLists]
  );

  const escalate = useCallback(
    async (orderId: number, comment: string) => {
      const order = await escalateOrder(orderId, comment);
      upsertInLists(order);
      await loadLists();
    },
    [upsertInLists, loadLists]
  );

  // reportShortage — единственное действие, которое возвращает значение
  // (заказ + инцидент), потому что диалог недостачи может его использовать.
  const reportShortage = useCallback(
    async (
      orderId: number,
      payload: {
        product_id: number;
        shortage_quantity: number;
        comment?: string;
      }
    ) => {
      const result = await reportShortageApi(orderId, payload);
      upsertInLists(result.order);
      await loadLists();
      return result;
    },
    [upsertInLists, loadLists]
  );

  const receive = useCallback(
    async (orderId: number) => {
      const order = await receiveTransfer(orderId);
      upsertInLists(order);
      await loadLists();
    },
    [upsertInLists, loadLists]
  );

  // Собираем всё в один объект и отдаём через Provider.
  // useMemo — чтобы объект не пересоздавался на каждый рендер (иначе
  // у детей всё время менялся бы контекст и всё перерисовывалось).
  const value: PickerContextValue = useMemo(
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
      meta,
      init,
      selectWarehouse,
      refresh,
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
      meta,
      init,
      selectWarehouse,
      refresh,
      loadOrder,
      take,
      release,
      complete,
      escalate,
      reportShortage,
      receive,
      resetWarehouse,
    ]
  );

  return (
    <PickerContext.Provider value={value}>{children}</PickerContext.Provider>
  );
}

/** Удобный хук: любая страница вызывает usePicker() и получает весь контекст.
 *  Если вызвать вне Provider — падаем с понятной ошибкой. */
export function usePicker(): PickerContextValue {
  const ctx = useContext(PickerContext);
  if (!ctx) throw new Error("usePicker must be used within PickerProvider");
  return ctx;
}

export type { PickerQueueFilters };

