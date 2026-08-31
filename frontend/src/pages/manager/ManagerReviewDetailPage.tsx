// React-хуки: useCallback — мемоизация загрузчика, useState — выбранное действие.
import { useCallback, useState } from "react";
// MUI-компоненты: контейнер, кнопка, чип, карточка, рейтинг, текст.
import { Box, Button, Chip, Paper, Rating, Typography } from "@mui/material";
// Иконка «назад».
import ArrowBackRoundedIcon from "@mui/icons-material/ArrowBackRounded";
// Роутинг: переходы и id из URL.
import { useNavigate, useParams } from "react-router-dom";
// API: отзыв и команды модерации.
import { fetchManagerReview, hideManagerReview, replyManagerReview, restoreManagerReview } from "../../manager/api";
// Форматирование даты и подписи причин модерации.
import { dateTime, moderationReasonLabel } from "../../manager/format";
// Хук загрузки данных.
import { useManagerQuery } from "../../manager/useManagerQuery";
// Контекст менеджера.
import { useManager } from "../../contexts/ManagerContext";
// Диалог с полем/селектом для действий.
import { ManagerActionDialog } from "../../components/manager/ManagerActionDialog";
// Чип статуса.
import { ManagerStatusChip } from "../../components/manager/ManagerStatusChip";
// История модерации.
import { ModerationHistory } from "../../components/manager/moderation/ModerationHistory";
// Состояния страницы.
import { ManagerErrorState, ManagerListSkeleton } from "../../components/manager/ManagerPageStates";
// CSS-модуль общих стилей.
import styles from "../../scss/pages/ManagerShared.module.scss";

// Варианты причин для селекта «Нарушение» (ключ + подпись).
const reasonOptions = Object.entries(moderationReasonLabel).map(([value, label]) => ({ value, label }));
// Возможные действия модератора.
type Action = "hide" | "restore" | "reply" | null;

// Детальная страница отзыва на товар.
export default function ManagerReviewDetailPage() {
  const id = Number(useParams().reviewId); // id отзыва из URL
  const navigate = useNavigate(); // переход к заказу
  const { online, notify } = useManager(); // интернет и тост
  const [action, setAction] = useState<Action>(null); // выбранное действие
  const loader = useCallback(() => fetchManagerReview(id), [id]); // загрузчик отзыва
  const query = useManagerQuery(loader, 60_000); // данные с опросом 60 сек
  const review = query.data; // отзыв
  // Первичная загрузка — скелетон.
  if (query.loading && !review) return <ManagerListSkeleton rows={2} />;
  // Ошибка при пустых данных.
  if (query.error && !review) return <ManagerErrorState error={query.error} onRetry={() => void query.refresh()} />;
  // Данных нет — пусто.
  if (!review) return null;

  // После команды: показать сообщение и тихо обновить отзыв.
  const changed = async (message: string) => { notify(message); await query.refresh(true); };
  return (
    <Box className={styles.page}>
      {/* Шапка: назад, товар, клиент и дата, чип статуса. */}
      <Box className={styles.pageHeader}><Box><Button startIcon={<ArrowBackRoundedIcon />} onClick={() => navigate("/manager/moderation")}>К модерации</Button><Typography component="h2" className={styles.pageTitle}>{review.product.name}</Typography><Typography className={styles.subtitle}>{review.customer.name} · {dateTime(review.created_at)}</Typography></Box><ManagerStatusChip status={review.status} /></Box>
      {/* Кнопки действий по правам; офлайн блокирует. */}
      <Box className={styles.actions}>{review.actions.can_hide && <Button color="error" disabled={!online} onClick={() => setAction("hide")}>Скрыть</Button>}{review.actions.can_restore && <Button color="success" disabled={!online} onClick={() => setAction("restore")}>Восстановить</Button>}{review.actions.can_reply && <Button variant="contained" disabled={!online} onClick={() => setAction("reply")}>{review.company_reply ? "Изменить ответ" : "Ответить"}</Button>}</Box>
      <Box className={styles.detailGrid}>
        <Box className={styles.wideColumn}>
          {/* Текст отзыва, оценка и признак подтверждённой покупки. */}
          <Paper className={styles.panel}><Box className={styles.cardHeader}><Rating value={review.rating} readOnly /><Chip size="small" variant="outlined" label={review.verified_purchase ? "Проверенная покупка" : "Без подтверждения"} /></Box><Typography className={styles.preWrap} sx={{ mt: 2 }}>{review.comment || "Без комментария"}</Typography></Paper>
          {/* Официальный ответ компании (если есть). */}
          {review.company_reply && <Paper className={styles.panel}><Typography className={styles.sectionTitle}>Официальный ответ</Typography><Typography className={styles.preWrap} sx={{ mt: 1 }}>{review.company_reply.body}</Typography><Typography className={styles.meta} sx={{ mt: 1 }}>{review.company_reply.author_name} · {dateTime(review.company_reply.edited_at ?? review.company_reply.created_at)}</Typography></Paper>}
          {/* История модерации. */}
          <ModerationHistory logs={review.moderation_history} />
        </Box>
        <Box className={styles.sideColumn}><Paper className={styles.panel}><Typography className={styles.sectionTitle}>Источник</Typography><Typography sx={{ mt: 1 }}>Клиент: {review.customer.name}</Typography><Typography className={styles.meta}>{review.customer.email}</Typography><Typography sx={{ mt: 1 }}>Заказ: {review.source_order?.id ?? "—"}</Typography>{review.source_order && <Button sx={{ mt: 1 }} onClick={() => navigate(`/manager/orders/${review.source_order!.id}`)}>Открыть заказ</Button>}</Paper></Box>
      </Box>
      {/* Диалог «Скрыть»: селект причины + комментарий модератора. */}
      <ManagerActionDialog open={action === "hide"} title="Скрыть отзыв" description="Требуются код нарушения и внутренний комментарий. Текст и оценка клиента не изменяются." select={{ name: "reason_code", label: "Нарушение", options: reasonOptions }} fieldName="comment" fieldLabel="Комментарий модератора" confirmLabel="Скрыть" danger onClose={() => setAction(null)} onSubmit={async ({ reason_code, comment }) => { const result = await hideManagerReview(id, reason_code, comment); await changed(result.message); }} />
      {/* Диалог «Восстановить»: причина восстановления. */}
      <ManagerActionDialog open={action === "restore"} title="Восстановить отзыв" fieldName="comment" fieldLabel="Причина восстановления" confirmLabel="Восстановить" onClose={() => setAction(null)} onSubmit={async ({ comment }) => { const result = await restoreManagerReview(id, comment); await changed(result.message); }} />
      {/* Диалог ответа компании (с предзаполненным текстом текущего ответа). */}
      <ManagerActionDialog open={action === "reply"} title="Официальный ответ компании" description="Повторное сохранение обновит единственный официальный ответ, не создавая второй." fieldName="body" fieldLabel="Текст ответа" initialValue={review.company_reply?.body ?? ""} maxLength={5000} confirmLabel="Сохранить ответ" onClose={() => setAction(null)} onSubmit={async ({ body }) => { const result = await replyManagerReview(id, body); await changed(result.message); }} />
    </Box>
  );
}
