import { useCallback, useEffect, useRef, useState } from "react";
import {
  http,
  HttpError,
  HttpMethod,
  HttpRequestOptions,
  HttpResponse,
} from "../utils/http";

interface UseHttpState<T> {
  data: T | null;
  loading: boolean;
  error: HttpError | null;
}

interface UseHttpReturn<T> extends UseHttpState<T> {
  /** Выполнить (или перевыполнить) запрос */
  execute: (
    executeUrl?: string | URL,
    executeMethod?: HttpMethod,
    executeBody?: any,
    executeOptions?: HttpRequestOptions
  ) => Promise<void>;
  /** Отменить текущий запрос */
  abort: () => void;
}

/** Конфигурация хука useHttp */
export interface UseHttpConfig<T> {
  url?: string | URL;
  method?: HttpMethod;
  body?: any;
  options?: HttpRequestOptions;
  /** Выполнить запрос автоматически при монтировании */
  autoExecute?: boolean;
  onSuccess?: (data: T) => void;
  onError?: (error: HttpError) => void;
}

/** Хук для HTTP-запросов с поддержкой отмены через AbortController.
 *  @param config — url, method, body, options, autoExecute, onSuccess, onError
 *  @returns { data, loading, error, execute, abort }
 *  При размонтировании компонента запрос автоматически отменяется. */
export function useHttp<T>(
  config: UseHttpConfig<T>
): UseHttpReturn<T> {
  const { url, method = "GET", body, options, autoExecute, onSuccess, onError } =
    config;

  const [state, setState] = useState<UseHttpState<T>>({
    data: null,
    loading: false,
    error: null,
  });

  const abortControllerRef = useRef<AbortController | null>(null);

  const execute = useCallback(
    async (
      executeUrl?: string | URL,
      executeMethod?: HttpMethod,
      executeBody?: any,
      executeOptions?: HttpRequestOptions
    ) => {
      const requestUrl = executeUrl || url;
      const requestMethod = executeMethod || method;
      const requestBody = executeBody || body;
      const requestOptions = executeOptions || options;

      if (!requestUrl) {
        throw new Error("URL is required for HTTP request");
      }

      // Отменить предыдущий запрос, если есть
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }

      const abortController = new AbortController();
      abortControllerRef.current = abortController;

      setState((prev) => ({ ...prev, loading: true, error: null }));

      try {
        let response: HttpResponse<T>;

        switch (requestMethod) {
          case "GET":
            response = await http.get<T>(requestUrl, {
              ...requestOptions,
              signal: abortController.signal,
            });
            break;
          case "POST":
            response = await http.post<T>(requestUrl, requestBody, {
              ...requestOptions,
              signal: abortController.signal,
            });
            break;
          case "PUT":
            response = await http.put<T>(requestUrl, requestBody, {
              ...requestOptions,
              signal: abortController.signal,
            });
            break;
          case "DELETE":
            response = await http.delete<T>(requestUrl, {
              ...requestOptions,
              signal: abortController.signal,
            });
            break;
          case "PATCH":
            response = await http.patch<T>(requestUrl, {
              ...requestOptions,
              signal: abortController.signal,
            });
            break;
        }

        if (!abortController.signal.aborted) {
          setState({
            data: response!.data,
            error: null,
            loading: false,
          });
          onSuccess?.(response!.data);
        }
      } catch (error) {
        // AbortError — нормальная отмена, не обрабатываем
        if (error instanceof Error && error.name === "AbortError") {
          return;
        }

        if (!abortController.signal.aborted) {
          const httpError = error as HttpError;
          setState((prev) => ({
            ...prev,
            loading: false,
            error: httpError,
          }));
          onError?.(httpError);
        }
      } finally {
        if (abortControllerRef.current === abortController) {
          abortControllerRef.current = null;
        }
      }
    },
    [url, method, body, options, onSuccess, onError]
  );

  /** Отменить текущий запрос */
  const abort = useCallback(() => {
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
      abortControllerRef.current = null;
      setState((prev) => ({ ...prev, loading: false }));
    }
  }, []);

  // Автоматическое выполнение при монтировании
  useEffect(() => {
    if (autoExecute && url) {
      execute();
    }
  }, [autoExecute, url]);

  // Отмена запроса при размонтировании
  useEffect(() => {
    return () => {
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
        abortControllerRef.current = null;
      }
    };
  }, []);

  return {
    ...state,
    execute,
    abort,
  };
}

export {};
