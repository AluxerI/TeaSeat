// Форматирование суммы в рубли по русской локали (Intl встроен в браузер).
export const money = (value: number) => new Intl.NumberFormat("ru-RU", {
  style: "currency", // денежный формат
  currency: "RUB", // валюта — рубль
  maximumFractionDigits: 2, // максимум 2 знака после запятой
}).format(value);

// Форматирование даты/времени: если значения нет — возвращаем "—".
export const dateTime = (value?: string | null) => value
  ? new Intl.DateTimeFormat("ru-RU", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value))
  : "—"; // плейсхолдер для пустой даты

// Локализованные названия каналов продаж (ключ — значение API).
export const channelLabel: Record<string, string> = {
  online: "Онлайн",
  seller: "Продажа в точке",
  internal: "Внутренний",
};

// Локализованные названия типов обращений клиентов.
export const requestTypeLabel: Record<string, string> = {
  change_delivery: "Изменение доставки",
  cancel_order: "Отмена заказа",
  order_problem: "Проблема с заказом",
  other: "Другое",
};

// Локализованные названия кодов причины при модерации.
export const moderationReasonLabel: Record<string, string> = {
  spam: "Спам",
  abuse: "Оскорбления",
  personal_data: "Персональные данные",
  off_topic: "Не по теме",
  fraud: "Мошенничество",
  other: "Другое",
};
