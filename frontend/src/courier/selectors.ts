import type {
  CourierDelivery,
  CourierListKey,
  CourierPwaState,
  DeliveryKind,
  DeliveryStatus,
} from "./types";

/**
 * Selector — обычная чистая функция для чтения state.
 * Он ничего не загружает и не изменяет: только превращает хранящиеся id в
 * готовый массив заказов или фильтрует его для конкретного экрана. Благодаря
 * этому компоненты не повторяют одинаковую логику и остаются проще.
 */
export function selectList(
  state: CourierPwaState,
  key: CourierListKey,
): CourierDelivery[] {
  return state.lists[key].ids
    .map((id) => state.deliveries[id])
    .filter((delivery): delivery is CourierDelivery => Boolean(delivery));
}

/** Backend может вернуть в ready_for_delivery и свободные, и уже свои заказы.
 * В свободной очереди показываем только явно разрешённые сервером `can_claim`. */
export const selectAvailableDeliveries = (deliveries: CourierDelivery[]) =>
  deliveries.filter((delivery) => delivery.actions.can_claim);

export function selectMineGroups(deliveries: CourierDelivery[]): Record<
  Exclude<DeliveryStatus, "delivered">,
  CourierDelivery[]
> {
  // Компонент получает уже готовые группы и отвечает только за их отображение.
  return {
    shipped: deliveries.filter((delivery) => delivery.status === "shipped"),
    ready_for_delivery: deliveries.filter(
      (delivery) => delivery.status === "ready_for_delivery",
    ),
    awaiting_receipt: deliveries.filter(
      (delivery) => delivery.status === "awaiting_receipt",
    ),
  };
}

export function filterByKind(
  deliveries: CourierDelivery[],
  kind: DeliveryKind | "all",
): CourierDelivery[] {
  return kind === "all"
    ? deliveries
    : deliveries.filter((delivery) => delivery.delivery_kind === kind);
}

export function deliveryDestination(delivery: CourierDelivery): string {
  // У обычного заказа адрес покупателя, у перемещения — адрес другого склада.
  if (delivery.delivery_kind === "transfer") {
    return [delivery.transfer_destination?.city, delivery.transfer_destination?.address]
      .filter(Boolean)
      .join(", ") || "Точка назначения не указана";
  }
  return delivery.delivery_address?.full_address || "Адрес не указан";
}

export function deliveryWindowLabel(delivery: CourierDelivery): string {
  const window = delivery.scheduled_window;
  return window ? `${window.date} · ${window.time_from}–${window.time_to}` : "Без интервала";
}

export function deliveryItemsLabel(delivery: CourierDelivery): string {
  // Количества могут одновременно быть в граммах и штуках, поэтому их нельзя
  // честно складывать в одно «ед.». В карточке считаем только позиции.
  return `${delivery.items.length} поз.`;
}

export function buildYandexMapsUrl(address: string): string {
  return `https://yandex.ru/maps/?text=${encodeURIComponent(address)}`;
}
