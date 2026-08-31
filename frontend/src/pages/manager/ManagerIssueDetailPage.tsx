// React-хуки: useCallback — мемоизация загрузчиков, useState — выбранное действие.
import { useCallback, useState } from "react";
// MUI-компоненты: баннер, контейнер, кнопка, чип, карточка, текст.
import { Alert, Box, Button, Chip, Paper, Typography } from "@mui/material";
// Иконка «назад».
import ArrowBackRoundedIcon from "@mui/icons-material/ArrowBackRounded";
// Роутинг: переходы и id из URL.
import { useNavigate, useParams } from "react-router-dom";
// API: проблема, затронутые заказы, смена статуса.
import { fetchAffectedOrders, fetchFulfillmentIssue, transitionFulfillmentIssue } from "../../manager/api";
// Форматирование даты и денег.
import { dateTime, money } from "../../manager/format";
// Хук загрузки данных.
import { useManagerQuery } from "../../manager/useManagerQuery";
// Контекст менеджера (интернет, уведомления, счётчики).
import { useManager } from "../../contexts/ManagerContext";
// Чип статуса.
import { ManagerStatusChip } from "../../components/manager/ManagerStatusChip";
// Диалог подтверждения действия.
import { ManagerConfirmDialog } from "../../components/manager/ManagerConfirmDialog";
// Состояния: ошибка и скелетон.
import { ManagerErrorState, ManagerListSkeleton } from "../../components/manager/ManagerPageStates";
// CSS-модуль общих стилей.
import styles from "../../scss/pages/ManagerShared.module.scss";

// Возможные действия над проблемой.
type Action = "take" | "release" | "close" | null;

// Детальная страница проблемы комплектации.
export default function ManagerIssueDetailPage() {
  const id = Number(useParams().issueId); // id проблемы из URL
  const navigate = useNavigate(); // переход к заказу
  const { online, notify, refreshCounters } = useManager(); // интернет, тост, счётчики
  const [action, setAction] = useState<Action>(null); // выбранное действие (для диалога)
  const issueLoader = useCallback(() => fetchFulfillmentIssue(id), [id]); // загрузчик проблемы
  const affectedLoader = useCallback(() => fetchAffectedOrders(id), [id]); // загрузчик кандидатов
  const issueQuery = useManagerQuery(issueLoader, 30_000); // данные проблемы
  const affectedQuery = useManagerQuery(affectedLoader, 30_000); // данные кандидатов
  const issue = issueQuery.data; // сама проблема

  // Первичная загрузка — скелетон.
  if (issueQuery.loading && !issue) return <ManagerListSkeleton rows={3} />;
  // Ошибка при пустых данных.
  if (issueQuery.error && !issue) return <ManagerErrorState error={issueQuery.error} onRetry={() => void issueQuery.refresh()} />;
  // Данных нет — пусто.
  if (!issue) return null;

  // Выполняет действие над проблемой и обновляет все данные.
  const run = async (next: Exclude<Action, null>) => {
    const result = await transitionFulfillmentIssue(id, next); // шлём команду
    notify(result.message); // показываем ответ сервера
    // Обновляем проблему, кандидатов и счётчики бейджей параллельно.
    await Promise.all([issueQuery.refresh(true), affectedQuery.refresh(true), refreshCounters()]);
  };

  return (
    <Box className={styles.page}>
      {/* Шапка: назад, название товара, склад и дата, чип статуса. */}
      <Box className={styles.pageHeader}>
        <Box><Button startIcon={<ArrowBackRoundedIcon />} onClick={() => navigate("/manager/issues")}>К проблемам</Button><Typography component="h2" className={styles.pageTitle}>{issue.product?.name ?? `Проблема #${id}`}</Typography><Typography className={styles.subtitle}>{issue.warehouse?.name} · {dateTime(issue.created_at)}</Typography></Box>
        <ManagerStatusChip status={issue.status} label={issue.status_name} />
      </Box>
      {/* Кнопки действий — только те, что разрешены; офлайн блокирует. */}
      <Box className={styles.actions}>
        {issue.actions.can_take && <Button variant="contained" disabled={!online} onClick={() => setAction("take")}>Взять в работу</Button>}
        {issue.actions.can_release && <Button disabled={!online} onClick={() => setAction("release")}>Вернуть в очередь</Button>}
        {issue.actions.can_close && <Button color="error" disabled={!online} onClick={() => setAction("close")}>Закрыть разбор</Button>}
      </Box>
      {/* Предупреждение о семантике закрытия. */}
      <Alert severity="warning">Закрытие означает только конец текущего разбора. Backend не помечает дефицит как автоматически разрешённый.</Alert>
      <Box className={styles.detailGrid}>
        <Box className={styles.wideColumn}>
          {/* Панель причины и цифр дефицита/резервов. */}
          <Paper className={styles.panel}><Typography className={styles.sectionTitle}>Причина</Typography><Typography sx={{ mt: 1 }}>{issue.reason_message}</Typography><Box className={styles.metricRow} sx={{ mt: 2, flexWrap: "wrap" }}><Chip color="error" label={`Дефицит: ${issue.shortage_quantity}`} /><Chip variant="outlined" label={`Online reserve: ${issue.reserved_online_before}`} /><Chip variant="outlined" label={`Seller reserve: ${issue.reserved_seller_before}`} /></Box></Paper>
          {/* Живой список заказов-кандидатов на перенос резерва. */}
          <Paper className={styles.panel}><Typography className={styles.sectionTitle}>Заказы-кандидаты</Typography><Typography className={styles.muted}>Это живой список резервов. Просмотр кандидата ничего не изменяет и не выбирает «жертву».</Typography>{affectedQuery.error && <ManagerErrorState error={affectedQuery.error} onRetry={() => void affectedQuery.refresh()} />}{affectedQuery.data?.data.map((order) => <Box key={order.fulfillment_order_id} className={styles.dividerRow}><Box className={styles.cardHeader}><Box><Typography sx={{ fontWeight: 750 }}>{order.customer_order_number}</Typography><Typography className={styles.meta}>{order.customer.name} · {order.delivery.address?.full_address ?? "без адреса"}</Typography></Box><Typography sx={{ fontWeight: 800 }}>{order.reserved_quantity} {order.stock_unit}</Typography></Box><Typography className={styles.meta}>{money(order.line_total)} · {order.customer_order_status}</Typography></Box>)}</Paper>
        </Box>
        <Box className={styles.sideColumn}>
          {/* Исходная продажа: заказ, продавец, сумма, переход к заказу. */}
          <Paper className={styles.panel}><Typography className={styles.sectionTitle}>Исходная продажа</Typography><Typography sx={{ mt: 1, fontWeight: 750 }}>{issue.source_order?.order_number ?? `#${issue.source_order_id}`}</Typography><Typography className={styles.meta}>Продавец: {issue.source_order?.seller?.name ?? "—"}</Typography><Typography className={styles.meta}>Сумма: {money(issue.source_order?.final_total ?? 0)}</Typography><Button sx={{ mt: 2 }} onClick={() => navigate(`/manager/orders/${issue.source_order_id}`)}>Открыть заказ</Button></Paper>
          {/* Ответственный менеджер. */}
          <Paper className={styles.panel}><Typography className={styles.sectionTitle}>Ответственный</Typography><Typography sx={{ mt: 1 }}>{issue.manager?.name ?? "Дело ещё не взято"}</Typography></Paper>
        </Box>
      </Box>
      {/* Диалог подтверждения выбранного действия. */}
      <ManagerConfirmDialog
        open={Boolean(action)} // открыт, если действие выбрано
        title={action === "take" ? "Взять дело?" : action === "release" ? "Вернуть дело в очередь?" : "Закрыть разбор?"} // заголовок по действию
        description={action === "close" ? "После закрытия Manager API не позволяет открыть дело повторно." : "Backend проверит владельца и актуальный статус под блокировкой."} // пояснение
        confirmLabel={action === "take" ? "Взять" : action === "release" ? "Вернуть" : "Закрыть"} // подпись кнопки
        danger={action === "close"} // закрытие — опасное действие (красная кнопка)
        onClose={() => setAction(null)} // отмена
        onConfirm={() => run(action!)} // подтверждение выполнения
      />
    </Box>
  );
}
