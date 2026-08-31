// React-хуки: useCallback — мемоизация загрузчика, useState — выбранное действие.
import { useCallback, useState } from "react";
// MUI-компоненты: контейнер, кнопка, чип, карточка, текст.
import { Box, Button, Chip, Paper, Typography } from "@mui/material";
// Иконка «назад».
import ArrowBackRoundedIcon from "@mui/icons-material/ArrowBackRounded";
// Роутинг: переходы и id из URL.
import { useNavigate, useParams } from "react-router-dom";
// API: обратная связь и команды модерации.
import { fetchManagerFeedbackItem, hideManagerFeedback, restoreManagerFeedback } from "../../manager/api";
// Форматирование даты и причин модерации.
import { dateTime, moderationReasonLabel } from "../../manager/format";
// Хук загрузки данных.
import { useManagerQuery } from "../../manager/useManagerQuery";
// Контекст менеджера.
import { useManager } from "../../contexts/ManagerContext";
// Диалог с полем/селектом.
import { ManagerActionDialog } from "../../components/manager/ManagerActionDialog";
// Чип статуса.
import { ManagerStatusChip } from "../../components/manager/ManagerStatusChip";
// История модерации.
import { ModerationHistory } from "../../components/manager/moderation/ModerationHistory";
// Состояния страницы.
import { ManagerErrorState, ManagerListSkeleton } from "../../components/manager/ManagerPageStates";
// CSS-модуль общих стилей.
import styles from "../../scss/pages/ManagerShared.module.scss";

// Варианты причин для селекта «Нарушение».
const reasonOptions = Object.entries(moderationReasonLabel).map(([value, label]) => ({ value, label }));

// Детальная страница оценки заказа (обратной связи).
export default function ManagerFeedbackDetailPage() {
  const id = Number(useParams().feedbackId); // id записи из URL
  const navigate = useNavigate(); // переход к заказу
  const { online, notify } = useManager(); // интернет и тост
  const [action, setAction] = useState<"hide" | "restore" | null>(null); // выбранное действие
  const loader = useCallback(() => fetchManagerFeedbackItem(id), [id]); // загрузчик записи
  const query = useManagerQuery(loader, 60_000); // данные с опросом 60 сек
  const item = query.data; // запись
  // Первичная загрузка — скелетон.
  if (query.loading && !item) return <ManagerListSkeleton rows={2} />;
  // Ошибка при пустых данных.
  if (query.error && !item) return <ManagerErrorState error={query.error} onRetry={() => void query.refresh()} />;
  // Данных нет — пусто.
  if (!item) return null;

  // После команды: тост и тихое обновление.
  const changed = async (message: string) => { notify(message); await query.refresh(true); };
  return (
    <Box className={styles.page}>
      {/* Шапка: назад, номер заказа, клиент и дата, чип статуса. */}
      <Box className={styles.pageHeader}><Box><Button startIcon={<ArrowBackRoundedIcon />} onClick={() => navigate("/manager/moderation?section=feedback")}>К оценкам</Button><Typography component="h2" className={styles.pageTitle}>Оценка заказа #{item.order_id}</Typography><Typography className={styles.subtitle}>{item.customer.name} · {dateTime(item.created_at)}</Typography></Box><ManagerStatusChip status={item.status} /></Box>
      {/* Кнопки действий; офлайн блокирует. */}
      <Box className={styles.actions}>{item.actions.can_hide && <Button color="error" disabled={!online} onClick={() => setAction("hide")}>Скрыть</Button>}{item.actions.can_restore && <Button color="success" disabled={!online} onClick={() => setAction("restore")}>Восстановить</Button>}</Box>
      <Box className={styles.detailGrid}>
        <Box className={styles.wideColumn}>
          {/* Оценки по категориям и комментарий клиента. */}
          <Paper className={styles.panel}><Typography className={styles.sectionTitle}>Оценки</Typography><Box className={styles.metricRow} sx={{ mt: 2, flexWrap: "wrap" }}>{Object.entries(item.ratings).map(([key, value]) => <Chip key={key} variant="outlined" label={`${key}: ${value ?? "—"}/5`} />)}</Box><Typography className={styles.preWrap} sx={{ mt: 2 }}>{item.comment || "Без комментария"}</Typography></Paper>
          {/* История модерации. */}
          <ModerationHistory logs={item.moderation_history} />
        </Box>
        <Box className={styles.sideColumn}><Paper className={styles.panel}><Typography className={styles.sectionTitle}>Заказ и клиент</Typography><Typography sx={{ mt: 1 }}>{item.customer.name}</Typography><Typography className={styles.meta}>{item.customer.email}</Typography><Button sx={{ mt: 2 }} onClick={() => navigate(`/manager/orders/${item.order_id}`)}>Открыть заказ</Button></Paper></Box>
      </Box>
      {/* Диалог «Скрыть» с причиной и комментарием. */}
      <ManagerActionDialog open={action === "hide"} title="Скрыть оценку заказа" select={{ name: "reason_code", label: "Нарушение", options: reasonOptions }} fieldName="comment" fieldLabel="Комментарий модератора" confirmLabel="Скрыть" danger onClose={() => setAction(null)} onSubmit={async ({ reason_code, comment }) => { const result = await hideManagerFeedback(id, reason_code, comment); await changed(result.message); }} />
      {/* Диалог «Восстановить» с причиной. */}
      <ManagerActionDialog open={action === "restore"} title="Восстановить оценку" fieldName="comment" fieldLabel="Причина восстановления" confirmLabel="Восстановить" onClose={() => setAction(null)} onSubmit={async ({ comment }) => { const result = await restoreManagerFeedback(id, comment); await changed(result.message); }} />
    </Box>
  );
}
