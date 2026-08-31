// Хуки React: useEffect — побочные эффекты, useState — локальное состояние.
import { useEffect, useState } from "react";

/** Даёт живой countdown для 429. Компонент не делает повтор сам: пользователь
 * не должен случайно повторить небезопасную команду после истечения таймера. */
export function useThrottleDeadline(retryAfterMs?: number) {
  const [deadline, setDeadline] = useState<number | null>(null); // момент, когда можно повторить
  const [remainingSeconds, setRemainingSeconds] = useState(0); // осталось секунд ожидания

  // При получении retryAfterMs пересчитываем deadline (сейчас + задержка).
  useEffect(() => {
    if (!retryAfterMs) {
      setDeadline(null); // задержки нет — сбрасываем
      setRemainingSeconds(0);
      return;
    }
    setDeadline(Date.now() + retryAfterMs); // фиксируем момент окончания ожидания
  }, [retryAfterMs]);

  // Каждую секунду обновляем оставшееся время до deadline.
  useEffect(() => {
    if (!deadline) return; // если deadline не задан — таймер не нужен
    const tick = () => setRemainingSeconds(Math.max(0, Math.ceil((deadline - Date.now()) / 1000))); // секунды до deadline
    tick(); // сразу считаем первое значение
    const id = window.setInterval(tick, 1000); // обновляем раз в секунду
    return () => window.clearInterval(id); // чистим таймер при размонтировании
  }, [deadline]);

  // Возвращаем флаг "ещё запрещено" и оставшиеся секунды.
  return { throttled: remainingSeconds > 0, remainingSeconds };
}
