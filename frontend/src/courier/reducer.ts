import type {
  CourierDelivery,
  CourierListKey,
  CourierNotice,
  CourierPwaState,
  DeliveryCommand,
  PaginationMeta,
} from "./types";
import type { StaffApiError } from "./errors";

/**
 * Action — короткое описание события: «список загружается», «команда прошла»
 * и т.д. Компоненты не меняют большой объект state руками, а отправляют reducer
 * одно из этих событий. TypeScript следит, чтобы у события были нужные данные.
 */
export type CourierAction =
  | { type: "network/changed"; online: boolean }
  | { type: "list/requested"; key: CourierListKey }
  | {
      type: "list/received";
      key: CourierListKey;
      deliveries: CourierDelivery[];
      meta: PaginationMeta;
      append: boolean;
      receivedAt: number;
    }
  | { type: "list/failed"; key: CourierListKey; error: StaffApiError }
  | { type: "delivery/received"; delivery: CourierDelivery }
  | { type: "command/requested"; deliveryId: number; command: DeliveryCommand }
  | { type: "command/succeeded"; delivery: CourierDelivery; notice: CourierNotice }
  | { type: "command/failed"; deliveryId: number; error: StaffApiError }
  | { type: "notice/dismissed" };

const emptyList = () => ({
  ids: [],
  phase: "idle" as const,
  error: null,
  meta: null,
  lastFetchedAt: null,
});

/** Создаёт чистое начальное состояние при первом открытии CourierProvider. */
export function createCourierPwaState(
  online = typeof navigator === "undefined" ? true : navigator.onLine,
): CourierPwaState {
  return {
    deliveries: {},
    lists: {
      queue: emptyList(),
      mine: emptyList(),
      history: emptyList(),
    },
    commands: {},
    online,
    lastSyncAt: null,
    notice: null,
  };
}

function indexDeliveries(
  current: Record<number, CourierDelivery>,
  deliveries: CourierDelivery[],
): Record<number, CourierDelivery> {
  // Копируем словарь и кладём каждый заказ по id. Старый state не изменяем.
  const next = { ...current };
  for (const delivery of deliveries) next[delivery.id] = delivery;
  return next;
}

function uniqueIds(ids: number[]): number[] {
  return [...new Set(ids)];
}

export function courierReducer(
  state: CourierPwaState,
  action: CourierAction,
): CourierPwaState {
  /**
   * Reducer — одна чистая функция, которая отвечает только за изменение state.
   * На вход получает предыдущий state и событие, на выход отдаёт новый state.
   * Здесь нет fetch/API и нет прямой мутации React-state: spread (`...state`)
   * создаёт новые объекты, после чего React безопасно обновляет интерфейс.
   */
  switch (action.type) {
    case "network/changed":
      return { ...state, online: action.online };

    case "list/requested":
      return {
        ...state,
        lists: {
          ...state.lists,
          [action.key]: {
            ...state.lists[action.key],
            phase: "loading",
            error: null,
          },
        },
      };

    case "list/received": {
      // В словаре delivery хранится один раз, а каждый список хранит только id.
      // Поэтому обновлённый заказ сразу одинаковый в Queue, Mine и History.
      const incomingIds = action.deliveries.map((delivery) => delivery.id);
      const ids = action.append
        ? uniqueIds([...state.lists[action.key].ids, ...incomingIds])
        : incomingIds;
      return {
        ...state,
        deliveries: indexDeliveries(state.deliveries, action.deliveries),
        lists: {
          ...state.lists,
          [action.key]: {
            ids,
            phase: "success",
            error: null,
            meta: action.meta,
            lastFetchedAt: action.receivedAt,
          },
        },
        lastSyncAt: action.receivedAt,
      };
    }

    case "list/failed":
      return {
        ...state,
        lists: {
          ...state.lists,
          [action.key]: {
            ...state.lists[action.key],
            phase: "error",
            error: action.error,
          },
        },
      };

    case "delivery/received":
      return {
        ...state,
        deliveries: { ...state.deliveries, [action.delivery.id]: action.delivery },
      };

    case "command/requested":
      return {
        ...state,
        commands: {
          ...state.commands,
          [action.deliveryId]: {
            type: action.command,
            phase: "pending",
            error: null,
          },
        },
      };

    case "command/succeeded": {
      // POST завершён: убираем признак загрузки, сохраняем серверную версию
      // заказа и просим Layout показать понятное уведомление.
      const commands = { ...state.commands };
      delete commands[action.delivery.id];
      return {
        ...state,
        deliveries: { ...state.deliveries, [action.delivery.id]: action.delivery },
        commands,
        notice: action.notice,
      };
    }

    case "command/failed":
      // Ошибку держим рядом с конкретным заказом: остальные карточки остаются
      // рабочими, а сообщение дополнительно показывается через Snackbar.
      return {
        ...state,
        commands: {
          ...state.commands,
          [action.deliveryId]: {
            type: state.commands[action.deliveryId]?.type ?? "claim",
            phase: "error",
            error: action.error,
          },
        },
        notice: { severity: "error", message: action.error.message },
      };

    case "notice/dismissed":
      return { ...state, notice: null };

    default:
      return state;
  }
}
