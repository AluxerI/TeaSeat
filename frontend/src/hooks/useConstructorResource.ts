import { useEffect, useState } from "react";
import { useOnlineStatus } from "./useOnlineStatus";
import { constructorError } from "../utils/constructorErrors";

/** Снимок привязан к ключу: старый ответ другой коробки/роли не попадает в UI. */
export function useConstructorResource<T>(key: string, enabled: boolean, load: (signal: AbortSignal) => Promise<T>) {
  const online = useOnlineStatus();
  const [attempt, setAttempt] = useState(0);
  const [state, setState] = useState<{ key: string; data: T | null; loading: boolean; error: string }>({ key: "", data: null, loading: true, error: "" });
  useEffect(() => {
    if (!enabled || !online) return;
    const controller = new AbortController();
    setState((prev) => ({ key, data: prev.key === key ? prev.data : null, loading: true, error: "" }));
    void load(controller.signal).then((data) => {
      if (!controller.signal.aborted) setState({ key, data, loading: false, error: "" });
    }).catch((reason) => {
      if (!controller.signal.aborted) setState((prev) => ({ key, data: prev.key === key ? prev.data : null, loading: false, error: constructorError(reason) }));
    });
    return () => controller.abort();
  }, [key, enabled, online, attempt, load]);
  const current = state.key === key ? state : { data: null, loading: true, error: "" };
  return {
    data: enabled ? current.data : null,
    loading: enabled && online && current.loading,
    error: !online ? "Нет соединения. Подключитесь к сети для загрузки конструктора." : current.error,
    retry: () => setAttempt((value) => value + 1),
    online,
  };
}
