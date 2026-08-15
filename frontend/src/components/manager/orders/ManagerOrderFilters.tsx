// MUI-компоненты: кнопка, выпадающие списки, поля ввода.
import { Button, MenuItem, TextField } from "@mui/material";
// React-хуки: useEffect — синхронизация поля поиска, useState — локальное значение.
import { useEffect, useState } from "react";
// Тип фильтров заказов.
import type { ManagerOrderFilters as Filters } from "../../../manager/types";
// CSS-модуль общих стилей.
import styles from "../../../scss/pages/ManagerShared.module.scss";

// Панель фильтров списка заказов.
export function ManagerOrderFilters({ filters, onChange }: { filters: Filters; onChange: (next: Filters) => void }) {
  const [search, setSearch] = useState(filters.search ?? ""); // локальное значение поля поиска
  // Синхронизируем поле, когда фильтры меняются извне (например, сброс).
  useEffect(() => setSearch(filters.search ?? ""), [filters.search]);

  // Патч фильтров: применяет изменения и всегда сбрасывает страницу на 1.
  const patch = (next: Partial<Filters>) => onChange({ ...filters, ...next, page: 1 });
  return (
    <div className={styles.filters}>
      {/* Поле поиска; применяется по Enter или кнопке «Найти». */}
      <TextField
        label="Номер, клиент, email или телефон"
        value={search}
        onChange={(event) => setSearch(event.target.value)} // ввод
        onKeyDown={(event) => { if (event.key === "Enter") patch({ search: search.trim() || undefined }); }} // поиск по Enter
      />
      {/* Фильтр по статусу заказа. */}
      <TextField select label="Статус" value={filters.status ?? ""} onChange={(e) => patch({ status: e.target.value || undefined })}>
        <MenuItem value="">Все статусы</MenuItem>
        <MenuItem value="pending">Ожидает</MenuItem>
        <MenuItem value="confirmed">Подтверждён</MenuItem>
        <MenuItem value="processing">Собирается</MenuItem>
        <MenuItem value="ready_for_delivery">Готов к доставке</MenuItem>
        <MenuItem value="shipped">В пути</MenuItem>
        <MenuItem value="delivered">Доставлен</MenuItem>
        <MenuItem value="completed">Завершён</MenuItem>
        <MenuItem value="manager_review">Проверка менеджера</MenuItem>
        <MenuItem value="cancelled">Отменён</MenuItem>
      </TextField>
      {/* Фильтр по каналу продаж. */}
      <TextField select label="Канал" value={filters.sales_channel ?? ""} onChange={(e) => patch({ sales_channel: (e.target.value || undefined) as Filters["sales_channel"] })}>
        <MenuItem value="">Все каналы</MenuItem>
        <MenuItem value="online">Онлайн</MenuItem>
        <MenuItem value="seller">Продажа в точке</MenuItem>
        <MenuItem value="internal">Внутренний</MenuItem>
      </TextField>
      {/* Фильтр «есть ли проблема»: строка "true"/"false" → boolean. */}
      <TextField select label="Проблема" value={filters.has_issue === undefined ? "" : String(filters.has_issue)} onChange={(e) => patch({ has_issue: e.target.value === "" ? undefined : e.target.value === "true" })}>
        <MenuItem value="">Любые</MenuItem>
        <MenuItem value="true">Есть проблема</MenuItem>
        <MenuItem value="false">Без проблем</MenuItem>
      </TextField>
      {/* Диапазон дат. */}
      <TextField type="date" label="С даты" InputLabelProps={{ shrink: true }} value={filters.date_from ?? ""} onChange={(e) => patch({ date_from: e.target.value || undefined })} />
      <TextField type="date" label="По дату" InputLabelProps={{ shrink: true }} value={filters.date_to ?? ""} onChange={(e) => patch({ date_to: e.target.value || undefined })} />
      {/* Применить поиск. */}
      <Button variant="contained" onClick={() => patch({ search: search.trim() || undefined })}>Найти</Button>
      {/* Сбросить все фильтры (остаётся только страница и размер). */}
      <Button variant="text" onClick={() => { setSearch(""); onChange({ page: 1, per_page: filters.per_page }); }}>Сбросить</Button>
    </div>
  );
}
