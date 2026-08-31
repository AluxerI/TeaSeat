// Импорт React-хуков и типа ReactNode для дочерних элементов.
import {
  createContext, // создание контекста
  useCallback, // мемоизация функций
  useContext, // чтение контекста в хуке
  useEffect, // побочные эффекты
  useMemo, // мемоизация значений
  useState, // локальное состояние
  type ReactNode, // тип для children
} from "react";
// API-функции: список проблем и обращений (для счётчиков-бейджей).
import { fetchFulfillmentIssues, fetchManagerRequests } from "../manager/api";
// Типы: уведомление и краткое описание точки.
import type { Notice, WarehouseBrief } from "../manager/types";
// Хук статуса интернета.
import { useOnlineStatus } from "../courier/useOnlineStatus";
// Хук видимости вкладки.
import { usePageVisibility } from "../courier/usePageVisibility";
// Хук авторизации (для получения данных пользователя).
import { useAuth } from "../hooks/useAuth";

// Ключ localStorage, где хранится выбранная рабочая точка менеджера.
const WAREHOUSE_KEY = "manager_warehouse_id";

// Значение контекста: всё общее для manager-страниц состояние.
export interface ManagerContextValue {
  online: boolean; // есть ли интернет
  warehouses: WarehouseBrief[]; // доступные пользователю точки
  warehouseId: number | null; // выбранная точка
  setWarehouseId: (id: number | null) => void; // смена выбранной точки
  counters: { issues: number; requests: number }; // счётчики для бейджей
  refreshCounters: () => Promise<void>; // обновить счётчики
  notice: Notice | null; // текущее уведомление (Snackbar)
  notify: (message: string, severity?: Notice["severity"]) => void; // показать уведомление
  dismissNotice: () => void; // скрыть уведомление
}

// Сам контекст; изначально null — хуки падают, если провайдера нет.
export const ManagerContext = createContext<ManagerContextValue | null>(null);

/**
 * Здесь хранится только состояние, общее для ВСЕХ manager-страниц: выбранная
 * точка, badges и Snackbar. Заказы/обращения/отзывы остаются в page-hooks,
 * иначе изменение одного списка перерисовывало бы весь кабинет.
 */
export function ManagerProvider({ children }: { children: ReactNode }) {
  const { user } = useAuth(); // текущий пользователь
  const online = useOnlineStatus(); // статус соединения
  const visible = usePageVisibility(); // видимость вкладки
  // Точки пользователя берём из данных профиля (work_locations).
  const warehouses = useMemo<WarehouseBrief[]>(() => user?.work_locations ?? [], [user?.work_locations]);
  // Выбранная точка инициализируется из localStorage.
  const [warehouseId, setWarehouseIdState] = useState<number | null>(() => {
    const id = Number(localStorage.getItem(WAREHOUSE_KEY)); // читаем сохранённый id
    return Number.isInteger(id) && id > 0 ? id : null; // валидируем и возвращаем
  });
  // Счётчики для бейджей (открытые проблемы и обращения).
  const [counters, setCounters] = useState({ issues: 0, requests: 0 });
  // Текущее уведомление (Snackbar).
  const [notice, setNotice] = useState<Notice | null>(null);

  // Если сохранённая точка пропала из списка доступных — сбрасываем выбор.
  useEffect(() => {
    if (warehouseId && !warehouses.some((item) => item.id === warehouseId)) {
      setWarehouseIdState(null); // очищаем состояние
      localStorage.removeItem(WAREHOUSE_KEY); // и удаляем из localStorage
    }
  }, [warehouseId, warehouses]);

  // Смена выбранной точки с сохранением в localStorage.
  const setWarehouseId = useCallback((id: number | null) => {
    setWarehouseIdState(id); // обновляем состояние
    if (id) localStorage.setItem(WAREHOUSE_KEY, String(id)); // сохраняем выбор
    else localStorage.removeItem(WAREHOUSE_KEY); // или удаляем
  }, []);

  // Загрузка счётчиков открытых проблем и обращений.
  const refreshCounters = useCallback(async () => {
    if (!online) return; // без интернета нечего обновлять
    // Запрашиваем оба списка параллельно (по 1 записи — нужны только summary).
    const [issues, requests] = await Promise.all([
      fetchFulfillmentIssues({ warehouse_id: warehouseId ?? undefined, per_page: 1 }),
      fetchManagerRequests({ per_page: 1 }),
    ]);
    // Считаем открытые: ожидают или на рассмотрении.
    setCounters({
      issues: issues.summary.waiting + issues.summary.in_review, // открытые проблемы
      requests: requests.summary.waiting + requests.summary.in_review, // открытые обращения
    });
  }, [online, warehouseId]);

  // Фоновый опрос счётчиков каждые 30 секунд (пока интернет и вкладка видима).
  useEffect(() => {
    if (!online || !visible) return; // условия для опроса
    let disposed = false; // флаг остановки цикла
    let timer: number | undefined; // id таймера

    const tick = async () => {
      try {
        await refreshCounters(); // обновляем счётчики
      } catch {
        // Badge вспомогательный: ошибка основного раздела будет показана внутри страницы.
      }
      if (!disposed) timer = window.setTimeout(tick, 30_000); // планируем следующий тик
    };
    void tick(); // запускаем первый тик сразу
    return () => {
      disposed = true; // помечаем остановку
      if (timer) window.clearTimeout(timer); // снимаем таймер
    };
  }, [online, refreshCounters, visible]);

  // Показать уведомление (по умолчанию "успех").
  const notify = useCallback((message: string, severity: Notice["severity"] = "success") => {
    setNotice({ message, severity }); // кладём в состояние
  }, []);
  // Скрыть уведомление.
  const dismissNotice = useCallback(() => setNotice(null), []);

  // Провайдер раздаёт всё состояние дочерним страницам кабинета.
  return (
    <ManagerContext.Provider value={{
      online, // статус интернета
      warehouses, // список точек
      warehouseId, // выбранная точка
      setWarehouseId, // смена точки
      counters, // счётчики бейджей
      refreshCounters, // обновление счётчиков
      notice, // уведомление
      notify, // показать уведомление
      dismissNotice, // скрыть уведомление
    }}>
      {children}
    </ManagerContext.Provider>
  );
}

// Хук доступа к контексту менеджера.
export function useManager(): ManagerContextValue {
  const ctx = useContext(ManagerContext); // читаем контекст
  if (!ctx) throw new Error("useManager must be used within ManagerProvider"); // защита от неправильного использования
  return ctx; // возвращаем значение контекста
}
