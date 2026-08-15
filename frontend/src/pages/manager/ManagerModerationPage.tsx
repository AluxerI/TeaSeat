// React-хуки: useCallback — мемоизация загрузчиков, useMemo — фильтров, useState — поле поиска.
import { useCallback, useMemo, useState } from "react";
// MUI-компоненты: контейнер, кнопки, выпадающий список, вкладки, поле, текст.
import { Box, Button, MenuItem, Tab, Tabs, TextField, Typography } from "@mui/material";
// Иконка обновления.
import RefreshRoundedIcon from "@mui/icons-material/RefreshRounded";
// Роутинг: переходы и query-параметры.
import { useNavigate, useSearchParams } from "react-router-dom";
// API: отзывы на товары и обратная связь по заказам.
import { fetchManagerFeedback, fetchManagerReviews } from "../../manager/api";
// Типы фильтров и статусов модерации.
import type { FeedbackFilters, ModerationStatus, ReviewFilters } from "../../manager/types";
// Хук загрузки данных.
import { useManagerQuery } from "../../manager/useManagerQuery";
// Карточки отзыва и оценки заказа.
import { ManagerFeedbackCard, ManagerReviewCard } from "../../components/manager/moderation/ManagerModerationCards";
// Пагинация.
import { ManagerPagination } from "../../components/manager/ManagerPagination";
// Состояния страницы.
import { ManagerEmptyState, ManagerErrorState, ManagerListSkeleton } from "../../components/manager/ManagerPageStates";
// CSS-модуль общих стилей.
import styles from "../../scss/pages/ManagerShared.module.scss";

// Страница модерации: отзывы о товарах и оценки заказов.
export default function ManagerModerationPage() {
  const navigate = useNavigate(); // переход к деталям
  const [params, setParams] = useSearchParams(); // query-параметры
  const section = params.get("section") === "feedback" ? "feedback" : "reviews"; // активный раздел
  const [search, setSearch] = useState(params.get("search") ?? ""); // поле поиска (локальное)
  const status = (params.get("status") as ModerationStatus | "all") || undefined; // статус из URL
  const rating = Number(params.get("rating")) || undefined; // оценка из URL
  const page = Number(params.get("page")) || 1; // страница из URL
  // Фильтры отзывов на товары.
  const reviewFilters = useMemo<ReviewFilters>(() => ({ status, rating, search: params.get("search") || undefined, page, per_page: 20 }), [page, params, rating, status]);
  // Фильтры обратной связи по заказам.
  const feedbackFilters = useMemo<FeedbackFilters>(() => ({ status, search: params.get("search") || undefined, page, per_page: 20 }), [page, params, status]);
  const reviewLoader = useCallback(() => fetchManagerReviews(reviewFilters), [reviewFilters]); // загрузчик отзывов
  const feedbackLoader = useCallback(() => fetchManagerFeedback(feedbackFilters), [feedbackFilters]); // загрузчик оценок
  // Загружаем только активный раздел (enabled = признак активности).
  const reviews = useManagerQuery(reviewLoader, 60_000, section === "reviews");
  const feedback = useManagerQuery(feedbackLoader, 60_000, section === "feedback");
  const activeQuery = section === "reviews" ? reviews : feedback; // активный запрос

  // Изменение фильтров: undefined/"" удаляет параметр, иначе — сохраняет в URL.
  const change = (patch: Record<string, string | number | undefined>) => {
    const out = new URLSearchParams(params); // копия текущих параметров
    Object.entries(patch).forEach(([key, value]) => value === undefined || value === "" ? out.delete(key) : out.set(key, String(value))); // применяем патч
    setParams(out); // обновляем URL
  };

  return (
    <Box className={styles.page}>
      {/* Шапка и кнопка обновления активного раздела. */}
      <Box className={styles.pageHeader}><Box><Typography component="h2" className={styles.pageTitle}>Модерация</Typography><Typography className={styles.subtitle}>Текст клиента не редактируется: только скрытие, восстановление и официальный ответ</Typography></Box><Button startIcon={<RefreshRoundedIcon />} disabled={activeQuery.refreshing} onClick={() => void activeQuery.refresh()}>Обновить</Button></Box>
      {/* Вкладки разделов. */}
      <Tabs value={section} onChange={(_, value) => change({ section: value === "reviews" ? undefined : value, page: undefined })} className={styles.tabs}><Tab value="reviews" label="Отзывы о товарах" /><Tab value="feedback" label="Оценки заказов" /></Tabs>
      {/* Сводка средних оценок по категориям (для раздела feedback). */}
      {section === "feedback" && feedback.data && <Box className={styles.metricRow} sx={{ flexWrap: "wrap" }}>{Object.entries(feedback.data.summary).map(([key, item]) => <Box key={key} className={styles.metric}><Typography className={styles.meta}>{key}</Typography><Typography className={styles.metricValue}>{item.average ?? "—"}</Typography><Typography className={styles.meta}>{item.count} оценок</Typography></Box>)}</Box>}
      {/* Фильтры: поиск, статус, оценка (только для отзывов), кнопка «Найти». */}
      <Box className={styles.filters}>
        <TextField label="Поиск по тексту, клиенту или товару" value={search} onChange={(e) => setSearch(e.target.value)} onKeyDown={(e) => { if (e.key === "Enter") change({ search: search.trim() || undefined, page: undefined }); }} />
        <TextField select label="Статус" value={status ?? ""} onChange={(e) => change({ status: e.target.value || undefined, page: undefined })}><MenuItem value="">Все</MenuItem><MenuItem value="published">Опубликовано</MenuItem><MenuItem value="hidden">Скрыто</MenuItem></TextField>
        {section === "reviews" && <TextField select label="Оценка" value={rating ?? ""} onChange={(e) => change({ rating: e.target.value || undefined, page: undefined })}><MenuItem value="">Любая</MenuItem>{[5, 4, 3, 2, 1].map((n) => <MenuItem key={n} value={n}>{n}</MenuItem>)}</TextField>}
        <Button variant="contained" onClick={() => change({ search: search.trim() || undefined, page: undefined })}>Найти</Button>
      </Box>
      {/* Состояния активного запроса и списки разделов. */}
      {activeQuery.loading && !activeQuery.data && <ManagerListSkeleton />}
      {activeQuery.error && !activeQuery.data && <ManagerErrorState error={activeQuery.error} onRetry={() => void activeQuery.refresh()} />}
      {section === "reviews" && reviews.data?.data.length === 0 && <ManagerEmptyState title="Отзывы не найдены" description="Измените параметры модерации." />}
      {section === "feedback" && feedback.data?.data.length === 0 && <ManagerEmptyState title="Оценки не найдены" description="Измените параметры модерации." />}
      {section === "reviews" && reviews.data && reviews.data.data.length > 0 && <Box className={styles.list}>{reviews.data.data.map((review) => <ManagerReviewCard key={review.id} review={review} onOpen={() => navigate(`/manager/moderation/reviews/${review.id}`)} />)}<ManagerPagination meta={reviews.data.meta} onChange={(next) => change({ page: next })} /></Box>}
      {section === "feedback" && feedback.data && feedback.data.data.length > 0 && <Box className={styles.list}>{feedback.data.data.map((item) => <ManagerFeedbackCard key={item.id} feedback={item} onOpen={() => navigate(`/manager/moderation/feedback/${item.id}`)} />)}<ManagerPagination meta={feedback.data.meta} onChange={(next) => change({ page: next })} /></Box>}
    </Box>
  );
}
