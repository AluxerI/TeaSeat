// React-хуки: useCallback — мемоизация функций, useEffect — побочные эффекты,
// useRef — стабильная ссылка на значение, useState — локальное состояние.
import { useCallback, useEffect, useRef, useState } from "react";
// Хук статуса интернета (online/offline) — используем в кабинете курьера.
import { useOnlineStatus } from "../courier/useOnlineStatus";
// Хук видимости вкладки страницы — не опрашиваем сервер в фоне.
import { usePageVisibility } from "../courier/usePageVisibility";
// Кастомный тип ошибки API и функция её создания.
import { StaffApiError, toStaffApiError } from "../staff/errors";

// Хук загрузки данных кабинета менеджера с поддержкой ручного обновления,
// тихого фонового опроса и корректной обработкой размонтирования.
export function useManagerQuery<T>(loader: () => Promise<T>, pollMs = 0, enabled = true) {
  const [data, setData] = useState<T | null>(null); // загруженные данные
  const [loading, setLoading] = useState(true); // первичная загрузка
  const [refreshing, setRefreshing] = useState(false); // обновление в процессе
  const [error, setError] = useState<StaffApiError | null>(null); // последняя ошибка
  const mounted = useRef(true); // флаг, что компонент ещё в DOM
  const online = useOnlineStatus(); // есть ли соединение с сетью
  const visible = usePageVisibility(); // видима ли вкладка

  // React StrictMode в dev делает mount → cleanup → mount. Поэтому ref надо
  // явно вернуть в true на повторной установке effect.
  useEffect(() => {
    mounted.current = true; // при монтировании взводим флаг
    return () => { mounted.current = false; }; // при размонтировании снимаем
  }, []);

  // Функция загрузки: silent=true не включает индикатор "обновляю".
  const refresh = useCallback(async (silent = false) => {
    if (!silent) setRefreshing(true); // показываем индикатор обновления
    try {
      const next = await loader(); // вызываем переданный загрузчик данных
      if (mounted.current) {
        setData(next); // сохраняем свежие данные
        setError(null); // сбрасываем ошибку после успеха
      }
      return next; // возвращаем данные (для возможной обработки вне хука)
    } catch (reason) {
      // Если это не наш тип ошибки — приводим к нему.
      const nextError = reason instanceof StaffApiError ? reason : toStaffApiError(reason);
      if (mounted.current) setError(nextError); // сохраняем ошибку в состояние
      throw nextError; // пробрасываем дальше для обработки вызывающим кодом
    } finally {
      if (mounted.current) {
        setLoading(false); // первичная загрузка завершена
        setRefreshing(false); // индикатор обновления выключен
      }
    }
  }, [loader]); // пересоздаём только при смене загрузчика

  // Первичная загрузка: срабатывает при включении или смене loader.
  useEffect(() => {
    if (!enabled) return; // если загрузка отключена — ничего не делаем
    setLoading(true); // включаем индикатор первичной загрузки
    void refresh().catch(() => undefined); // запускаем, ошибку уже обработали в refresh
  }, [enabled, refresh]);

  // Фоновый опрос: тихо обновляет данные, пока есть интернет и вкладка видима.
  useEffect(() => {
    if (!enabled || !pollMs || !online || !visible) return; // условия для опроса
    let disposed = false; // флаг отмены таймера
    let timer = window.setTimeout(async function tick() {
      try {
        await refresh(true); // тихое обновление без индикатора
      } catch {
        // Сохраняем старые данные и ошибку; следующий tick попробует снова.
      }
      if (!disposed) timer = window.setTimeout(tick, pollMs); // планируем следующий тик
    }, pollMs); // первый тик через pollMs
    return () => {
      disposed = true; // помечаем цикл как остановленный
      window.clearTimeout(timer); // снимаем таймер при размонтировании
    };
  }, [enabled, online, pollMs, refresh, visible]);

  // Возвращаем данные, сеттер (для локальных правок), флаги и функцию обновления.
  return { data, setData, loading, refreshing, error, refresh };
}
