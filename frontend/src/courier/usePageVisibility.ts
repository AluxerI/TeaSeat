import { useEffect, useState } from "react";

/**
 * Возвращает false для свёрнутой PWA/скрытой вкладки. Страница передаёт это
 * значение в polling и не расходует сеть в фоне. После возвращения true
 * создаёт таймер заново, а первый запрос выполняется сразу.
 */
export function usePageVisibility(): boolean {
  const [visible, setVisible] = useState(() =>
    typeof document === "undefined" ? true : document.visibilityState === "visible",
  );

  useEffect(() => {
    const handleVisibility = () => setVisible(document.visibilityState === "visible");
    document.addEventListener("visibilitychange", handleVisibility);
    return () => document.removeEventListener("visibilitychange", handleVisibility);
  }, []);

  return visible;
}
