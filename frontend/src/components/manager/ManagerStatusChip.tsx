// Чип MUI для отображения статуса.
import { Chip } from "@mui/material";

// Цвет чипа для каждого внутреннего кода статуса.
const colorByStatus: Record<string, "default" | "primary" | "success" | "warning" | "error" | "info"> = {
  pending: "warning", // ожидает обработки
  confirmed: "info", // подтверждён
  processing: "primary", // собирается
  ready_for_delivery: "success", // готов к доставке
  shipped: "info", // в пути
  delivered: "success", // доставлен
  completed: "success", // завершён
  cancelled: "error", // отменён
  seller_review: "warning", // проверка продавца
  manager_review: "error", // проверка менеджера
  waiting: "warning", // проблема/обращение в очереди
  in_review: "info", // проблема/обращение в работе
  closed: "default", // проблема закрыта
  resolved: "success", // обращение решено
  rejected: "error", // обращение отклонено
  withdrawn: "default", // обращение отозвано
  published: "success", // отзыв опубликован
  hidden: "error", // отзыв скрыт
};

// Человекочитаемая подпись для каждого статуса (русский текст).
const labelByStatus: Record<string, string> = {
  pending: "Ожидает",
  confirmed: "Подтверждён",
  processing: "Собирается",
  ready_for_delivery: "Готов к доставке",
  shipped: "В пути",
  delivered: "Доставлен",
  completed: "Завершён",
  cancelled: "Отменён",
  seller_review: "Проверка продавца",
  manager_review: "Проверка менеджера",
  waiting: "В очереди",
  in_review: "В работе",
  closed: "Закрыто",
  resolved: "Решено",
  rejected: "Отклонено",
  withdrawn: "Отозвано",
  published: "Опубликовано",
  hidden: "Скрыто",
};

// Чип статуса: цвет и подпись по коду, приоритет — переданной подписи.
export function ManagerStatusChip({ status, label }: { status: string; label?: string }) {
  return <Chip size="small" color={colorByStatus[status] ?? "default"} label={label ?? labelByStatus[status] ?? status} />;
}
