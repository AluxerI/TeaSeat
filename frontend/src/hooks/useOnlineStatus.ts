import { useEffect, useState } from "react";

/**
 * navigator.onLine даёт синхронный стартовый снимок, а события window
 * online/offline делают его реактивным. Это признак наличия сети у браузера,
 * но не гарантия доступности backend — ошибки API обрабатываются отдельно.
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
