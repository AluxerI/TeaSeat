import { useEffect, useRef } from "react";

interface RecursivePollingOptions {
  enabled: boolean;
  intervalMs: number;
  refresh: () => Promise<unknown>;
  /** Для уже загруженного picker-снимка первый повтор можно отложить. */
  runImmediately?: boolean;
}
/**
 * Рекурсивный setTimeout ждёт завершения запроса и только потом планирует
 * следующий. Поэтому медленный API не создаёт параллельные запросы, а cleanup
 * гарантированно выключает таймер при offline, скрытии страницы или unmount.
 */
export function useRecursivePolling({
  enabled,
  intervalMs,
  refresh,
  runImmediately = true,
}: RecursivePollingOptions): void {
  const refreshRef = useRef(refresh);

  useEffect(() => {
    refreshRef.current = refresh;
  }, [refresh]);

  useEffect(() => {
    if (!enabled) return;

    let cancelled = false;
    let timer: ReturnType<typeof setTimeout> | undefined;

    const tick = async () => {
      if (cancelled) return;
      try {
        await refreshRef.current();
      } finally {
        if (!cancelled) timer = setTimeout(tick, intervalMs);
      }
    };

    if (runImmediately) void tick();
    else timer = setTimeout(tick, intervalMs);

    return () => {
      cancelled = true;
      if (timer) clearTimeout(timer);
    };
  }, [enabled, intervalMs, runImmediately]);
}
