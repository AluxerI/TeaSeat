// React-хуки: useCallback — мемоизация загрузчика, useMemo — фильтров.
import { useCallback, useMemo } from "react";
// MUI-компоненты: контейнер, кнопки, чекбокс/переключатель, выпадающий список,
// вкладки, текстовое поле, текст.
import { Box, Button, FormControlLabel, MenuItem, Switch, Tab, Tabs, TextField, Typography } from "@mui/material";
// Иконка обновления.
import RefreshRoundedIcon from "@mui/icons-material/RefreshRounded";
// Роутинг: переход к деталям, чтение/запись query-параметров.
import { useNavigate, useSearchParams } from "react-router-dom";
// Контекст менеджера (рабочая точка).
import { useManager } from "../../contexts/ManagerContext";
// API: список проблем комплектации.
import { fetchFulfillmentIssues } from "../../manager/api";
// Типы: фильтры проблем и статус.
import type { IssueFilters, IssueStatus } from "../../manager/types";
// Хук загрузки данных с опросом.
import { useManagerQuery } from "../../manager/useManagerQuery";
// Карточка проблемы в списке.
import { ManagerIssueCard } from "../../components/manager/issues/ManagerIssueCard";
// Пагинация.
import { ManagerPagination } from "../../components/manager/ManagerPagination";
// Состояния страницы.
import { ManagerEmptyState, ManagerErrorState, ManagerListSkeleton } from "../../components/manager/ManagerPageStates";
// CSS-модуль общих стилей.
import styles from "../../scss/pages/ManagerShared.module.scss";

// Страница списка проблем комплектации.
export default function ManagerIssuesPage() {
  const navigate = useNavigate(); // переход к деталям проблемы
  const [params, setParams] = useSearchParams(); // query-параметры
  const { warehouseId } = useManager(); // рабочая точка
  // Фильтры из URL + рабочая точка.
  const filters = useMemo<IssueFilters>(() => ({
    status: (params.get("status") as IssueStatus | "all") || undefined, // статус
    reason: params.get("reason") || undefined, // причина
    mine: params.get("mine") === "1" || undefined, // только мои
    warehouse_id: warehouseId ?? undefined, // точка
    page: Number(params.get("page")) || 1, // страница
    per_page: 20, // размер страницы
  }), [params, warehouseId]);
  const loader = useCallback(() => fetchFulfillmentIssues(filters), [filters]); // загрузчик
  const query = useManagerQuery(loader, 30_000); // данные с опросом 30 сек

  // Частичное изменение фильтров и запись в URL.
  const change = (patch: Partial<IssueFilters>) => {
    const next = { ...filters, ...patch }; // сливаем старые и новые фильтры
    const out = new URLSearchParams(); // новая query-строка
    if (next.status) out.set("status", next.status); // статус
    if (next.reason) out.set("reason", next.reason); // причина
    if (next.mine) out.set("mine", "1"); // только мои
    if ((next.page ?? 1) > 1) out.set("page", String(next.page)); // страница
    setParams(out); // применяем к URL
  };
  const summary = query.data?.summary; // счётчики по статусам

  return (
    <Box className={styles.page}>
      {/* Шапка и кнопка обновления. */}
      <Box className={styles.pageHeader}>
        <Box><Typography component="h2" className={styles.pageTitle}>Проблемы комплектации</Typography><Typography className={styles.subtitle}>Дефициты после физических продаж и расхождения остатков</Typography></Box>
        <Button startIcon={<RefreshRoundedIcon />} disabled={query.refreshing} onClick={() => void query.refresh()}>Обновить</Button>
      </Box>
      {/* Вкладки по статусам; "open" — сумма открытых (waiting + in_review). */}
      <Tabs value={filters.status ?? "open"} onChange={(_, value) => change({ status: value === "open" ? undefined : value, page: 1 })} variant="scrollable" className={styles.tabs}>
        <Tab value="open" label={`Открытые${summary ? ` (${summary.waiting + summary.in_review})` : ""}`} />
        <Tab value="waiting" label={`В очереди${summary ? ` (${summary.waiting})` : ""}`} />
        <Tab value="in_review" label={`В работе${summary ? ` (${summary.in_review})` : ""}`} />
        <Tab value="closed" label={`Закрытые${summary ? ` (${summary.closed})` : ""}`} />
        <Tab value="all" label="Все" />
      </Tabs>
      {/* Дополнительные фильтры: причина и «только мои». */}
      <Box className={styles.filters}>
        <TextField select label="Причина" value={filters.reason ?? ""} onChange={(e) => change({ reason: e.target.value || undefined, page: 1 })}>
          <MenuItem value="">Все причины</MenuItem>
          <MenuItem value="online_reservation_conflict">Конфликт online-резерва</MenuItem>
          <MenuItem value="physical_stock_discrepancy">Расхождение физического остатка</MenuItem>
        </TextField>
        <FormControlLabel control={<Switch checked={Boolean(filters.mine)} onChange={(_, checked) => change({ mine: checked || undefined, page: 1 })} />} label="Только мои" />
      </Box>
      {/* Состояния загрузки: скелетон / ошибка / пусто / список с пагинацией. */}
      {query.loading && !query.data && <ManagerListSkeleton />}
      {query.error && !query.data && <ManagerErrorState error={query.error} onRetry={() => void query.refresh()} />}
      {query.data?.data.length === 0 && <ManagerEmptyState title="Проблем нет" description="Для выбранного фильтра складские дела отсутствуют." />}
      {query.data && query.data.data.length > 0 && <Box className={styles.list}>{query.error && <ManagerErrorState error={query.error} onRetry={() => void query.refresh()} />}{query.data.data.map((issue) => <ManagerIssueCard key={issue.id} issue={issue} onOpen={() => navigate(`/manager/issues/${issue.id}`)} />)}<ManagerPagination meta={query.data.meta} onChange={(page) => change({ page })} /></Box>}
    </Box>
  );
}
