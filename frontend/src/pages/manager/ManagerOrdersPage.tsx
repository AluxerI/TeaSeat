// React-хуки: useCallback — мемоизация функции загрузки, useMemo — фильтров.
import { useCallback, useMemo } from "react";
// MUI-компоненты: контейнер, кнопка обновления, заголовок.
import { Box, Button, Typography } from "@mui/material";
// Иконка обновления.
import RefreshRoundedIcon from "@mui/icons-material/RefreshRounded";
// Роутинг: переход на детальную страницу, чтение/запись query-параметров.
import { useNavigate, useSearchParams } from "react-router-dom";
// API: получение списка заказов.
import { fetchManagerOrders } from "../../manager/api";
// Тип фильтров заказов (псевдоним Filters для краткости).
import type { ManagerOrderFilters as Filters } from "../../manager/types";
// Хук загрузки данных с фоновым опросом.
import { useManagerQuery } from "../../manager/useManagerQuery";
// Общий контекст менеджера (выбранная рабочая точка).
import { useManager } from "../../contexts/ManagerContext";
// Компонент формы фильтров заказов.
import { ManagerOrderFilters } from "../../components/manager/orders/ManagerOrderFilters";
// Карточка заказа в списке.
import { ManagerOrderCard } from "../../components/manager/orders/ManagerOrderCard";
// Компонент пагинации.
import { ManagerPagination } from "../../components/manager/ManagerPagination";
// Состояния страницы: пустой список, ошибка, скелетон загрузки.
import { ManagerEmptyState, ManagerErrorState, ManagerListSkeleton } from "../../components/manager/ManagerPageStates";
// CSS-модуль общих стилей кабинета.
import styles from "../../scss/pages/ManagerShared.module.scss";

// Читает фильтры из query-строки URL и дополняет выбранной рабочей точкой.
function readFilters(params: URLSearchParams, warehouseId: number | null): Filters {
  const page = Number(params.get("page")); // номер страницы из URL
  return {
    search: params.get("search") || undefined, // текстовый поиск
    status: params.get("status") || undefined, // статус заказа
    sales_channel: (params.get("channel") || undefined) as Filters["sales_channel"], // канал продаж
    has_issue: params.has("issue") ? params.get("issue") === "1" : undefined, // только с проблемами
    date_from: params.get("from") || undefined, // начало периода
    date_to: params.get("to") || undefined, // конец периода
    warehouse_id: warehouseId ?? undefined, // выбранная точка (если есть)
    page: Number.isInteger(page) && page > 0 ? page : 1, // страница (по умолчанию 1)
    per_page: 20, // фиксированный размер страницы
  };
}

// Страница списка заказов менеджера.
export default function ManagerOrdersPage() {
  const navigate = useNavigate(); // переход к деталям заказа
  const [params, setParams] = useSearchParams(); // работа с query-параметрами
  const { warehouseId } = useManager(); // выбранная рабочая точка
  const filters = useMemo(() => readFilters(params, warehouseId), [params, warehouseId]); // фильтры из URL
  const loader = useCallback(() => fetchManagerOrders(filters), [filters]); // загрузчик списка
  const query = useManagerQuery(loader, 45_000); // данные с опросом раз в 45 сек

  // Записывает новые фильтры в URL (обновляет и перечитывает данные).
  const changeFilters = (next: Filters) => {
    const out = new URLSearchParams(); // собираем новую query-строку
    if (next.search) out.set("search", next.search); // поиск
    if (next.status) out.set("status", next.status); // статус
    if (next.sales_channel) out.set("channel", next.sales_channel); // канал
    if (next.has_issue !== undefined) out.set("issue", next.has_issue ? "1" : "0"); // проблемы
    if (next.date_from) out.set("from", next.date_from); // начало периода
    if (next.date_to) out.set("to", next.date_to); // конец периода
    if ((next.page ?? 1) > 1) out.set("page", String(next.page)); // страница (только если > 1)
    setParams(out); // применяем к URL — сработает перезагрузка данных
  };

  return (
    <Box className={styles.page}>
      {/* Шапка страницы: заголовок и кнопка ручного обновления. */}
      <Box className={styles.pageHeader}>
        <Box><Typography component="h2" className={styles.pageTitle}>Заказы</Typography><Typography className={styles.subtitle}>Корневые заказы всех доступных рабочих точек</Typography></Box>
        <Button startIcon={<RefreshRoundedIcon />} onClick={() => void query.refresh()} disabled={query.refreshing}>Обновить</Button>
      </Box>
      {/* Панель фильтров; изменение фильтра меняет URL. */}
      <ManagerOrderFilters filters={filters} onChange={changeFilters} />
      {/* Первичная загрузка — скелетон списка. */}
      {query.loading && !query.data && <ManagerListSkeleton />}
      {/* Ошибка при пустых данных — блок ошибки с повтором. */}
      {query.error && !query.data && <ManagerErrorState error={query.error} onRetry={() => void query.refresh()} />}
      {/* Данные пришли, но список пуст — пустое состояние. */}
      {query.data?.data.length === 0 && <ManagerEmptyState title="Заказы не найдены" description="Измените фильтры или проверьте выбранную рабочую точку." />}
      {/* Есть заказы — рисуем список с карточками и пагинацией. */}
      {query.data && query.data.data.length > 0 && (
        <Box className={styles.list}>
          {/* Ошибка при наличии данных — показываем сверху списка. */}
          {query.error && <ManagerErrorState error={query.error} onRetry={() => void query.refresh()} />}
          {/* Карточка каждого заказа, клик ведёт на детальную страницу. */}
          {query.data.data.map((order) => <ManagerOrderCard key={order.id} order={order} onOpen={() => navigate(`/manager/orders/${order.id}`)} />)}
          {/* Пагинация: смена страницы пересобирает URL. */}
          <ManagerPagination meta={query.data.meta} onChange={(page) => changeFilters({ ...filters, page })} />
        </Box>
      )}
    </Box>
  );
}
