import { useEffect, useState } from "react";

/**
 * Сразу читает navigator.onLine, а затем реактивно слушает browser-события
 * `online` и `offline`. Это ответ на вопрос «у устройства есть сеть?», но не
 * гарантия доступности нашего backend — серверные сбои отдельно поймает API.
 */
export function useOnlineStatus(): boolean {
  const [online, setOnline] = useState(() =>
    typeof navigator === "undefined" ? true : navigator.onLine,
  );

  useEffect(() => {
    const handleOnline = () => setOnline(true);
    const handleOffline = () => setOnline(false);
    window.addEventListener("online", handleOnline);
    window.addEventListener("offline", handleOffline);
    return () => {
      window.removeEventListener("online", handleOnline);
      window.removeEventListener("offline", handleOffline);
    };
  }, []);

  return online;
}
