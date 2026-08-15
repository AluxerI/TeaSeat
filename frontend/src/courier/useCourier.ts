import { useContext, useEffect, useState } from "react";
import { CourierContext, type CourierContextValue } from "./CourierContext";
import { toStaffApiError, type StaffApiError } from "./errors";
import type { CourierDelivery } from "./types";

export function useCourier(): CourierContextValue {
  // Хук прячет useContext и заодно выдаёт понятную ошибку, если разработчик
  // случайно использовал курьерский компонент вне CourierProvider.
  const context = useContext(CourierContext);
  if (!context) throw new Error("useCourier must be used within CourierProvider");
  return context;
}

/**
 * Возвращает заказ из общего state без ожидания. Если пользователь открыл
 * прямую ссылку и кеш пуст, хук сам запрашивает заказ и сообщает loading/error.
 */
export function useCourierDelivery(id: number | null) {
  const { state, loadDelivery } = useCourier();
  const [loading, setLoading] = useState(Boolean(id && !state.deliveries[id]));
  const [error, setError] = useState<StaffApiError | null>(null);
  const delivery = id ? state.deliveries[id] ?? null : null;

  useEffect(() => {
    if (!id || delivery) return;
    let active = true;
    setLoading(true);
    loadDelivery(id)
      .catch((cause) => {
        if (active) setError(toStaffApiError(cause));
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [delivery, id, loadDelivery]);

  return { delivery, loading, error };
}
