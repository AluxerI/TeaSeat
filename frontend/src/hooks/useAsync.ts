import { useCallback, useEffect, useRef, useState } from "react";

/** Состояние асинхронного запроса */
type HookAsync<T> = {
  data: T | null;
  loading: boolean;
  error: Error | null | string;
  /** Повторно выполнить запрос */
  execute: () => Promise<any>;
};

/** Хук для асинхронных вызовов.
 *  @param asyncFunction — функция, возвращающая Promise
 *  @param immadiate — выполнить сразу при монтировании (по умолчанию true)
 *  @returns { data, loading, error, execute }
 *  Защищает от обновления состояния на размонтированном компоненте. */
export const useAsync = <T>(
  asyncFunction: () => Promise<T>,
  immadiate: boolean = true
): HookAsync<T> => {
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<Error | null | string>(null);
  const mounted = useRef(true);

  const execute = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await asyncFunction();

      if (mounted.current) {
        setData(result);
      }

      return result;
    } catch (err) {
      if (mounted.current) {
        setError(err instanceof Error ? err.message : "Unknown error");
      }
      throw err;
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    mounted.current = true;
    if (immadiate) {
      execute();
    }
    return () => {
      mounted.current = false;
    };
  }, []);

  return { data, loading, error, execute };
};
