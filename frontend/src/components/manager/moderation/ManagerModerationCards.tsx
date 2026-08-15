// MUI-компоненты карточек: контейнер, чип, карточка, рейтинг, текст.
import { Box, Chip, Paper, Rating, Typography } from "@mui/material";
// Типы обратной связи и отзыва.
import type { ManagerFeedback, ManagerReview } from "../../../manager/types";
// Форматирование даты.
import { dateTime } from "../../../manager/format";
// Чип статуса.
import { ManagerStatusChip } from "../ManagerStatusChip";
// CSS-модуль общих стилей.
import styles from "../../../scss/pages/ManagerShared.module.scss";

// Карточка отзыва на товар в списке модерации.
export function ManagerReviewCard({ review, onOpen }: { review: ManagerReview; onOpen: () => void }) {
  return (
    <Paper className={styles.card} role="button" tabIndex={0} onClick={onOpen} onKeyDown={(e) => { if (e.key === "Enter") onOpen(); }}>
      {/* Заголовок: товар, клиент и дата, статус модерации. */}
      <Box className={styles.cardHeader}><Box><Typography className={styles.cardTitle}>{review.product.name}</Typography><Typography className={styles.meta}>{review.customer.name} · {dateTime(review.created_at)}</Typography></Box><ManagerStatusChip status={review.status} /></Box>
      <Rating value={review.rating} readOnly size="small" sx={{ mt: 1 }} /> {/* оценка (1-5) */}
      <Typography className={styles.preWrap} sx={{ mt: 1 }}>{review.comment || "Без комментария"}</Typography> {/* текст отзыва */}
      {/* Признаки: подтверждённая покупка и наличие ответа компании. */}
      <Box className={styles.metricRow} sx={{ mt: 2 }}>{review.verified_purchase && <Chip size="small" color="success" variant="outlined" label="Проверенная покупка" />}{review.company_reply && <Chip size="small" variant="outlined" label="Есть ответ компании" />}</Box>
    </Paper>
  );
}

// Карточка обратной связи по доставке в списке модерации.
export function ManagerFeedbackCard({ feedback, onOpen }: { feedback: ManagerFeedback; onOpen: () => void }) {
  const ratings = Object.entries(feedback.ratings).filter(([, value]) => value !== null); // заполненные оценки по категориям
  return (
    <Paper className={styles.card} role="button" tabIndex={0} onClick={onOpen} onKeyDown={(e) => { if (e.key === "Enter") onOpen(); }}>
      {/* Заголовок: заказ, клиент и дата, статус. */}
      <Box className={styles.cardHeader}><Box><Typography className={styles.cardTitle}>Заказ #{feedback.order_id}</Typography><Typography className={styles.meta}>{feedback.customer.name} · {dateTime(feedback.created_at)}</Typography></Box><ManagerStatusChip status={feedback.status} /></Box>
      {/* Оценки по категориям. */}
      <Box className={styles.metricRow} sx={{ mt: 1, flexWrap: "wrap" }}>{ratings.map(([key, value]) => <Chip key={key} size="small" variant="outlined" label={`${key}: ${value}/5`} />)}</Box>
      {/* Комментарий клиента. */}
      <Typography className={styles.preWrap} sx={{ mt: 1 }}>{feedback.comment || "Без комментария"}</Typography>
    </Paper>
  );
}
