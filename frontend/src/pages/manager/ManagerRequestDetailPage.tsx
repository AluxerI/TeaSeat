// React-хуки: useCallback — мемоизация загрузчика, useState — выбранное действие.
import { useCallback, useState } from "react";
// MUI-компоненты: баннер, контейнер, кнопка, карточка, текст.
import { Alert, Box, Button, Paper, Typography } from "@mui/material";
// Иконка «назад».
import ArrowBackRoundedIcon from "@mui/icons-material/ArrowBackRounded";
// Роутинг: переходы и id из URL.
import { useNavigate, useParams } from "react-router-dom";
// API: обращение и смена его статуса.
import { fetchManagerRequest, transitionManagerRequest } from "../../manager/api";
// Форматирование даты и названия типа.
import { dateTime, requestTypeLabel } from "../../manager/format";
// Хук загрузки данных.
import { useManagerQuery } from "../../manager/useManagerQuery";
// Контекст менеджера.
import { useManager } from "../../contexts/ManagerContext";
// Диалог с текстовым полем (для ответа клиенту).
import { ManagerActionDialog } from "../../components/manager/ManagerActionDialog";
// Диалог простого подтверждения (взять/вернуть).
import { ManagerConfirmDialog } from "../../components/manager/ManagerConfirmDialog";
// Чип статуса.
import { ManagerStatusChip } from "../../components/manager/ManagerStatusChip";
// Состояния страницы.
import { ManagerErrorState, ManagerListSkeleton } from "../../components/manager/ManagerPageStates";
// CSS-модуль общих стилей.
import styles from "../../scss/pages/ManagerShared.module.scss";

// Возможные действия над обращением.
type Action = "take" | "release" | "resolve" | "reject" | null;

// Детальная страница обращения клиента.
export default function ManagerRequestDetailPage() {
  const id = Number(useParams().requestId); // id обращения из URL
  const navigate = useNavigate(); // переход к заказу
  const { online, notify, refreshCounters } = useManager(); // интернет, тост, счётчики
  const [action, setAction] = useState<Action>(null); // выбранное действие
  const loader = useCallback(() => fetchManagerRequest(id), [id]); // загрузчик обращения
  const query = useManagerQuery(loader, 30_000); // данные с опросом 30 сек
  const item = query.data; // обращение

  // Первичная загрузка — скелетон.
  if (query.loading && !item) return <ManagerListSkeleton rows={2} />;
  // Ошибка при пустых данных.
  if (query.error && !item) return <ManagerErrorState error={query.error} onRetry={() => void query.refresh()} />;
  // Данных нет — пусто.
  if (!item) return null;

  // Выполняет действие с опциональным комментарием менеджера.
  const run = async (next: Exclude<Action, null>, comment?: string) => {
    const result = await transitionManagerRequest(id, next, comment); // шлём команду
    notify(result.message); // показываем ответ сервера
    // Обновляем обращение и счётчики бейджей.
    await Promise.all([query.refresh(true), refreshCounters()]);
  };

  return (
    <Box className={styles.page}>
      {/* Шапка: назад, тип обращения, заказ и дата, чип статуса. */}
      <Box className={styles.pageHeader}>
        <Box><Button startIcon={<ArrowBackRoundedIcon />} onClick={() => navigate("/manager/requests")}>К обращениям</Button><Typography component="h2" className={styles.pageTitle}>{requestTypeLabel[item.type] ?? item.type}</Typography><Typography className={styles.subtitle}>{item.order?.order_number ?? `Заказ #${item.order_id}`} · {dateTime(item.created_at)}</Typography></Box>
        <ManagerStatusChip status={item.status} />
      </Box>
      {/* Кнопки действий, доступные по правам; офлайн блокирует. */}
      <Box className={styles.actions}>
        {item.actions.can_take && <Button variant="contained" disabled={!online} onClick={() => setAction("take")}>Взять в работу</Button>}
        {item.actions.can_release && <Button disabled={!online} onClick={() => setAction("release")}>Вернуть в очередь</Button>}
        {item.actions.can_resolve && <Button color="success" variant="contained" disabled={!online} onClick={() => setAction("resolve")}>Завершить</Button>}
        {item.actions.can_reject && <Button color="error" disabled={!online} onClick={() => setAction("reject")}>Отклонить</Button>}
      </Box>
      {/* Подсказка по порядку действий при изменении заказа. */}
      <Alert severity="info">Если клиент просит изменить сам заказ, сначала выполните команду в карточке заказа, затем завершите обращение с понятным ответом.</Alert>
      <Box className={styles.detailGrid}>
        <Box className={styles.wideColumn}>
          {/* Сообщение клиента и ответ менеджера (если есть). */}
          <Paper className={styles.panel}><Typography className={styles.sectionTitle}>Сообщение клиента</Typography><Typography className={styles.preWrap} sx={{ mt: 1 }}>{item.message || "Комментарий отсутствует"}</Typography>{item.manager_comment && <><Typography className={styles.meta} sx={{ mt: 2 }}>Ответ менеджера</Typography><Typography className={styles.preWrap}>{item.manager_comment}</Typography></>}</Paper>
        </Box>
        <Box className={styles.sideColumn}>
          {/* Данные клиента. */}
          <Paper className={styles.panel}><Typography className={styles.sectionTitle}>Клиент</Typography><Typography sx={{ mt: 1, fontWeight: 750 }}>{item.customer?.name ?? "—"}</Typography><Typography className={styles.meta}>{item.customer?.phone}</Typography><Typography className={styles.meta}>{item.customer?.email}</Typography></Paper>
          {/* Связанный заказ и переход к нему. */}
          <Paper className={styles.panel}><Typography className={styles.sectionTitle}>Связанный заказ</Typography><Typography sx={{ mt: 1 }}>{item.order?.order_number}</Typography><Typography className={styles.meta}>Статус: {item.order?.status}</Typography><Button sx={{ mt: 2 }} onClick={() => navigate(`/manager/orders/${item.order_id}`)}>Открыть заказ</Button></Paper>
        </Box>
      </Box>
      {/* Диалог подтверждения для взять/вернуть. */}
      <ManagerConfirmDialog open={action === "take" || action === "release"} title={action === "take" ? "Взять обращение?" : "Вернуть обращение?"} description="Backend не позволит двум менеджерам одновременно владеть одним обращением." confirmLabel={action === "take" ? "Взять" : "Вернуть"} onClose={() => setAction(null)} onConfirm={() => run(action as "take" | "release")} />
      {/* Диалог с полем ответа клиенту для завершить/отклонить. */}
      <ManagerActionDialog open={action === "resolve" || action === "reject"} title={action === "resolve" ? "Завершить обращение" : "Отклонить обращение"} description="Этот комментарий увидит клиент." fieldName="manager_comment" fieldLabel="Ответ клиенту" maxLength={5000} confirmLabel={action === "resolve" ? "Завершить" : "Отклонить"} danger={action === "reject"} onClose={() => setAction(null)} onSubmit={({ manager_comment }) => run(action as "resolve" | "reject", manager_comment)} />
    </Box>
  );
}
