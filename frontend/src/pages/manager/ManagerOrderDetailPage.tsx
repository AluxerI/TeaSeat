// React-хук: useCallback — мемоизация загрузчика заказа.
import { useCallback } from "react";
// MUI-компоненты: контейнер, кнопки, чип, текст.
import { Box, Button, Chip, Typography } from "@mui/material";
// Иконка «назад» и обновления.
import ArrowBackRoundedIcon from "@mui/icons-material/ArrowBackRounded";
import RefreshRoundedIcon from "@mui/icons-material/RefreshRounded";
// Роутинг: переходы и id заказа из URL.
import { useNavigate, useParams } from "react-router-dom";
// API: загрузка деталей заказа.
import { fetchManagerOrder } from "../../manager/api";
// Форматирование: подпись канала, дата/время, деньги.
import { channelLabel, dateTime, money } from "../../manager/format";
// Хук загрузки данных с фоновым опросом.
import { useManagerQuery } from "../../manager/useManagerQuery";
// Чип статуса заказа.
import { ManagerStatusChip } from "../../components/manager/ManagerStatusChip";
// Панель действий менеджера над заказом.
import { ManagerOrderActions } from "../../components/manager/orders/ManagerOrderActions";
// Состав заказа (позиции и наборы).
import { ManagerOrderItems } from "../../components/manager/orders/ManagerOrderItems";
// Дополнительные панели детальной страницы.
import { CustomerPaymentPanel, DeliveryPanel, FulfillmentPanel, IssuesPanel, NotesPanel, TechnicalHistory } from "../../components/manager/orders/ManagerOrderPanels";
// Состояния: ошибка и скелетон загрузки.
import { ManagerErrorState, ManagerListSkeleton } from "../../components/manager/ManagerPageStates";
// CSS-модуль общих стилей кабинета.
import styles from "../../scss/pages/ManagerShared.module.scss";

// Детальная страница заказа менеджера.
export default function ManagerOrderDetailPage() {
  const id = Number(useParams().orderId); // id заказа из URL
  const navigate = useNavigate(); // переход назад к списку
  const loader = useCallback(() => fetchManagerOrder(id), [id]); // загрузчик заказа
  const query = useManagerQuery(loader, 30_000); // данные с опросом раз в 30 сек
  const order = query.data; // загруженный заказ

  // Первичная загрузка — скелетон.
  if (query.loading && !order) return <ManagerListSkeleton rows={3} />;
  // Ошибка при пустых данных — блок ошибки.
  if (query.error && !order) return <ManagerErrorState error={query.error} onRetry={() => void query.refresh()} />;
  // Данных нет и не загружается — ничего не рисуем.
  if (!order) return null;

  return (
    <Box className={styles.page}>
      {/* Шапка: назад, номер заказа, статус/канал/дата/сумма, обновление. */}
      <Box className={styles.pageHeader}>
        <Box>
          <Button startIcon={<ArrowBackRoundedIcon />} onClick={() => navigate("/manager/orders")}>К заказам</Button>
          <Typography component="h2" className={styles.pageTitle}>{order.order_number}</Typography>
          <Box className={styles.metricRow} sx={{ mt: 1, flexWrap: "wrap" }}>
            <ManagerStatusChip status={order.status} label={order.status_name} /> {/* чип статуса */}
            <Chip size="small" variant="outlined" label={channelLabel[order.sales_channel] ?? order.sales_channel} /> {/* канал продаж */}
            <Typography className={styles.meta}>{dateTime(order.timestamps.created_at)}</Typography> {/* дата создания */}
            <Typography sx={{ fontWeight: 800 }}>{money(order.totals.final_total)}</Typography> {/* итоговая сумма */}
          </Box>
        </Box>
        <Button startIcon={<RefreshRoundedIcon />} disabled={query.refreshing} onClick={() => void query.refresh()}>Обновить</Button>
      </Box>
      {/* Ошибка при наличии данных — предупреждение сверху. */}
      {query.error && <ManagerErrorState error={query.error} onRetry={() => void query.refresh()} />}
      {/* Действия менеджера (подтвердить/отменить/перенести/изменить состав). */}
      <ManagerOrderActions order={order} onChanged={() => query.refresh(true)} />
      {/* Двухколоночная сетка: широкая и боковая колонки. */}
      <Box className={styles.detailGrid}>
        <Box className={styles.wideColumn}>
          <ManagerOrderItems order={order} onChanged={() => query.refresh(true)} /> {/* состав заказа */}
          <FulfillmentPanel order={order} /> {/* части фулфилмента */}
          <IssuesPanel order={order} /> {/* проблемы комплектации */}
          <NotesPanel order={order} /> {/* заметки клиента и внутренние */}
          <TechnicalHistory order={order} /> {/* история статусов и движений */}
        </Box>
        <Box className={styles.sideColumn}>
          <CustomerPaymentPanel order={order} /> {/* клиент и оплата */}
          <DeliveryPanel order={order} /> {/* доставка */}
        </Box>
      </Box>
    </Box>
  );
}
