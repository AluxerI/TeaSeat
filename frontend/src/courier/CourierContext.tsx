import {
  createContext,
  useCallback,
  useEffect,
  useMemo,
  useReducer,
  useState,
  type ReactNode,
} from "react";
import {
  claimCourierDelivery,
  deliverCourierDelivery,
  fetchCourierDeliveries,
  fetchCourierDelivery,
  releaseCourierDelivery,
  startCourierDelivery,
} from "./api";
import { StaffApiError, toStaffApiError } from "./errors";
import { persistCourierState, restoreCourierState } from "./cache";
import { courierReducer } from "./reducer";
import { selectList } from "./selectors";
import { useOnlineStatus } from "./useOnlineStatus";
import type {
  CourierDelivery,
  CourierDeliveryFilters,
  CourierListKey,
  CourierPwaState,
  DeliveryCommand,
} from "./types";

export interface CourierContextValue {
  state: CourierPwaState;
  queue: CourierDelivery[];
  mine: CourierDelivery[];
  history: CourierDelivery[];
  warehouseId: number | null;
  setWarehouseId(id: number | null): void;
  refreshList(
    key: CourierListKey,
    filters?: CourierDeliveryFilters,
    append?: boolean,
  ): Promise<void>;
  loadNextPage(key: CourierListKey, filters?: CourierDeliveryFilters): Promise<void>;
  loadDelivery(id: number, force?: boolean): Promise<CourierDelivery>;
  claim(id: number): Promise<CourierDelivery>;
  release(id: number, reason: string): Promise<CourierDelivery>;
  start(id: number): Promise<CourierDelivery>;
  deliver(id: number): Promise<CourierDelivery>;
  dismissNotice(): void;
}

export const CourierContext = createContext<CourierContextValue | null>(null);

/**
 * Provider — единый «координатор» раздела /courier.
 * Он хранит state, вызывает API и отправляет reducer события о результате.
 * Благодаря этому страницы не передают данные через длинную цепочку props.
 * Сам Context — только канал доступа; удобный useCourier находится отдельно.
 */
export function CourierProvider({ children }: { children: ReactNode }) {
  const online = useOnlineStatus();
  const [state, dispatch] = useReducer(
    courierReducer,
    online,
    restoreCourierState,
  );
  const [warehouseId, setWarehouseId] = useState<number | null>(null);

  useEffect(() => {
    dispatch({ type: "network/changed", online });
  }, [online]);

  // Context переживает переходы между страницами, Web Storage — перезапуск
  // вкладки. Сохраняется только успешный GET-state, но не команды и ошибки.
  useEffect(() => {
    persistCourierState(state);
  }, [state]);

  const refreshList = useCallback(
    async (
      key: CourierListKey,
      filters: CourierDeliveryFilters = {},
      append = false,
    ) => {
      // В offline не затираем сохранённые карточки сетевой ошибкой. Кнопки POST
      // всё равно блокируются отдельно, а polling возобновится после online.
      if (!online) return;
      // Сначала включаем loading, затем заменяем его данными или понятной ошибкой.
      dispatch({ type: "list/requested", key });
      try {
        const response = await fetchCourierDeliveries({
          ...filters,
          ...(warehouseId !== null ? { warehouse_id: warehouseId } : {}),
        });
        dispatch({
          type: "list/received",
          key,
          deliveries: response.data,
          meta: response.meta,
          append,
          receivedAt: Date.now(),
        });
      } catch (error) {
        const apiError = toStaffApiError(error);
        dispatch({ type: "list/failed", key, error: apiError });
      }
    },
    [online, warehouseId],
  );

  const loadNextPage = useCallback(
    async (key: CourierListKey, filters: CourierDeliveryFilters = {}) => {
      const meta = state.lists[key].meta;
      if (!meta || meta.current_page >= meta.last_page) return;
      await refreshList(key, { ...filters, page: meta.current_page + 1 }, true);
    },
    [refreshList, state.lists],
  );

  const loadDelivery = useCallback(
    async (id: number, force = false): Promise<CourierDelivery> => {
      const cached = state.deliveries[id];
      if (cached && !force) return cached;
      const delivery = await fetchCourierDelivery(id);
      dispatch({ type: "delivery/received", delivery });
      return delivery;
    },
    [state.deliveries],
  );

  /**
   * Общий сценарий всех POST-команд. Локально статус заранее не угадываем:
   * ждём ответ backend, сохраняем присланный заказ и обновляем списки.
   */
  const runCommand = useCallback(
    async (
      id: number,
      command: DeliveryCommand,
      request: () => ReturnType<typeof claimCourierDelivery>,
    ): Promise<CourierDelivery> => {
      if (!online) {
        const error = new StaffApiError("offline", "Для этого действия нужен интернет");
        dispatch({ type: "command/failed", deliveryId: id, error });
        throw error;
      }

      dispatch({ type: "command/requested", deliveryId: id, command });
      try {
        const response = await request();
        dispatch({
          type: "command/succeeded",
          delivery: response.data,
          notice: { severity: "success", message: response.message },
        });

        // Команда может переместить доставку Queue → Mine → History.
        // Сверяем списки, не пытаясь самостоятельно воспроизвести правила backend.
        await Promise.all([
          refreshList("queue", { status: "ready_for_delivery", per_page: 50 }),
          refreshList("mine", { mine: true, per_page: 50 }),
          response.data.status === "delivered"
            ? refreshList("history", {
                mine: true,
                status: "delivered",
                per_page: 20,
              })
            : Promise.resolve(),
        ]);
        return response.data;
      } catch (error) {
        const apiError = toStaffApiError(error);
        dispatch({ type: "command/failed", deliveryId: id, error: apiError });

        // 409 означает, что состояние уже изменилось на сервере. Повторять POST
        // нельзя, но GET безопасно возвращает актуальную картину.
        if (apiError.kind === "conflict") {
          await Promise.allSettled([
            loadDelivery(id, true),
            refreshList("queue", { status: "ready_for_delivery", per_page: 50 }),
            refreshList("mine", { mine: true, per_page: 50 }),
          ]);
        }
        throw apiError;
      }
    },
    [loadDelivery, online, refreshList],
  );

  const claim = useCallback(
    (id: number) => runCommand(id, "claim", () => claimCourierDelivery(id)),
    [runCommand],
  );
  const release = useCallback(
    (id: number, reason: string) =>
      runCommand(id, "release", () => releaseCourierDelivery(id, reason)),
    [runCommand],
  );
  const start = useCallback(
    (id: number) => runCommand(id, "start", () => startCourierDelivery(id)),
    [runCommand],
  );
  const deliver = useCallback(
    (id: number) => runCommand(id, "deliver", () => deliverCourierDelivery(id)),
    [runCommand],
  );
  const dismissNotice = useCallback(() => dispatch({ type: "notice/dismissed" }), []);

  const value = useMemo<CourierContextValue>(
    () => ({
      state,
      queue: selectList(state, "queue"),
      mine: selectList(state, "mine"),
      history: selectList(state, "history"),
      warehouseId,
      setWarehouseId,
      refreshList,
      loadNextPage,
      loadDelivery,
      claim,
      release,
      start,
      deliver,
      dismissNotice,
    }),
    [
      state,
      warehouseId,
      setWarehouseId,
      refreshList,
      loadNextPage,
      loadDelivery,
      claim,
      release,
      start,
      deliver,
      dismissNotice,
    ],
  );

  return <CourierContext.Provider value={value}>{children}</CourierContext.Provider>;
}
