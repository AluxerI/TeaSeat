import { useEffect, useState } from "react";

/** Останавливает polling, когда PWA свёрнута или вкладка скрыта. */
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
