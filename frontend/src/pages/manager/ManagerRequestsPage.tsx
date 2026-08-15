// React-хуки: useCallback — мемоизация загрузчика, useMemo — фильтров.
import { useCallback, useMemo } from "react";
// MUI-компоненты: контейнер, кнопки, переключатель, выпадающий список, вкладки, текст.
import { Box, Button, FormControlLabel, MenuItem, Switch, Tab, Tabs, TextField, Typography } from "@mui/material";
// Иконка обновления.
import RefreshRoundedIcon from "@mui/icons-material/RefreshRounded";
// Роутинг: переходы и query-параметры.
import { useNavigate, useSearchParams } from "react-router-dom";
// API: список обращений клиентов.
import { fetchManagerRequests } from "../../manager/api";
// Типы: фильтры и статусы обращений.
import type { RequestFilters, RequestStatus, RequestType } from "../../manager/types";
// Хук загрузки данных.
import { useManagerQuery } from "../../manager/useManagerQuery";
// Карточка обращения.
import { ManagerRequestCard } from "../../components/manager/requests/ManagerRequestCard";
// Пагинация.
import { ManagerPagination } from "../../components/manager/ManagerPagination";
// Состояния страницы.
import { ManagerEmptyState, ManagerErrorState, ManagerListSkeleton } from "../../components/manager/ManagerPageStates";
// CSS-модуль общих стилей.
import styles from "../../scss/pages/ManagerShared.module.scss";

// Страница списка обращений клиентов.
export default function ManagerRequestsPage() {
  const navigate = useNavigate(); // переход к деталям обращения
  const [params, setParams] = useSearchParams(); // query-параметры
  // Фильтры из URL.
  const filters = useMemo<RequestFilters>(() => ({
    status: (params.get("status") as RequestStatus | "all") || undefined, // статус
    type: (params.get("type") as RequestType) || undefined, // тип обращения
    mine: params.get("mine") === "1" || undefined, // только мои
    page: Number(params.get("page")) || 1, // страница
    per_page: 20, // размер страницы
  }), [params]);
  const loader = useCallback(() => fetchManagerRequests(filters), [filters]); // загрузчик
  const query = useManagerQuery(loader, 30_000); // данные с опросом 30 сек
  // Частичное изменение фильтров и запись в URL.
  const change = (patch: Partial<RequestFilters>) => {
    const next = { ...filters, ...patch }; // новые фильтры
    const out = new URLSearchParams(); // новая query-строка
    if (next.status) out.set("status", next.status); // статус
    if (next.type) out.set("type", next.type); // тип
    if (next.mine) out.set("mine", "1"); // только мои
    if ((next.page ?? 1) > 1) out.set("page", String(next.page)); // страница
    setParams(out); // применяем к URL
  };
  const summary = query.data?.summary; // счётчики по статусам

  return (
    <Box className={styles.page}>
      {/* Шапка страницы и кнопка обновления. */}
      <Box className={styles.pageHeader}>
        <Box><Typography component="h2" className={styles.pageTitle}>Обращения клиентов</Typography><Typography className={styles.subtitle}>Обращение сообщает о действии, но само не меняет заказ</Typography></Box>
        <Button startIcon={<RefreshRoundedIcon />} disabled={query.refreshing} onClick={() => void query.refresh()}>Обновить</Button>
      </Box>
      {/* Вкладки по статусам; "open" — сумма открытых. */}
      <Tabs value={filters.status ?? "open"} onChange={(_, value) => change({ status: value === "open" ? undefined : value, page: 1 })} variant="scrollable" className={styles.tabs}>
        <Tab value="open" label={`Открытые${summary ? ` (${summary.waiting + summary.in_review})` : ""}`} />
        <Tab value="waiting" label={`В очереди${summary ? ` (${summary.waiting})` : ""}`} />
        <Tab value="in_review" label={`В работе${summary ? ` (${summary.in_review})` : ""}`} />
        <Tab value="resolved" label="Решённые" />
        <Tab value="rejected" label="Отклонённые" />
        <Tab value="all" label="Все" />
      </Tabs>
      {/* Дополнительные фильтры: тип и «только мои». */}
      <Box className={styles.filters}>
        <TextField select label="Тип" value={filters.type ?? ""} onChange={(e) => change({ type: (e.target.value || undefined) as RequestType | undefined, page: 1 })}>
          <MenuItem value="">Все типы</MenuItem><MenuItem value="change_delivery">Изменение доставки</MenuItem><MenuItem value="cancel_order">Отмена заказа</MenuItem><MenuItem value="order_problem">Проблема с заказом</MenuItem><MenuItem value="other">Другое</MenuItem>
        </TextField>
        <FormControlLabel control={<Switch checked={Boolean(filters.mine)} onChange={(_, checked) => change({ mine: checked || undefined, page: 1 })} />} label="Только мои" />
      </Box>
      {/* Состояния: скелетон / ошибка / пусто / список. */}
      {query.loading && !query.data && <ManagerListSkeleton />}
      {query.error && !query.data && <ManagerErrorState error={query.error} onRetry={() => void query.refresh()} />}
      {query.data?.data.length === 0 && <ManagerEmptyState title="Обращений нет" description="Для выбранного фильтра очередь пуста." />}
      {query.data && query.data.data.length > 0 && <Box className={styles.list}>{query.error && <ManagerErrorState error={query.error} onRetry={() => void query.refresh()} />}{query.data.data.map((item) => <ManagerRequestCard key={item.id} item={item} onOpen={() => navigate(`/manager/requests/${item.id}`)} />)}<ManagerPagination meta={query.data.meta} onChange={(page) => change({ page })} /></Box>}
    </Box>
  );
}
