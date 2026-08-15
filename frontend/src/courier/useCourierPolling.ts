import { useEffect, useRef } from "react";

interface CourierPollingOptions {
  enabled: boolean;
  intervalMs: number;
  refresh: () => Promise<unknown>;
}

/**
 * Периодически обновляет только открытую страницу.
 * Рекурсивный setTimeout запускается после завершения прошлого запроса, поэтому
 * медленный интернет не создаст несколько одновременных refresh. Когда enabled
 * становится false (offline, скрытая вкладка или unmount), таймер очищается.
 */
export function useCourierPolling({
  enabled,
  intervalMs,
  refresh,
}: CourierPollingOptions): void {
  const refreshRef = useRef(refresh);

  useEffect(() => {
    refreshRef.current = refresh;
  }, [refresh]);

  useEffect(() => {
    if (!enabled) return;

    let cancelled = false;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let requestRunning = false;

    const tick = async () => {
      if (cancelled || requestRunning) return;
      requestRunning = true;
      try {
        await refreshRef.current();
      } finally {
        requestRunning = false;
        if (!cancelled) timer = setTimeout(tick, intervalMs);
      }
    };

    void tick();

    return () => {
      cancelled = true;
      if (timer) clearTimeout(timer);
    };
  }, [enabled, intervalMs]);
}
