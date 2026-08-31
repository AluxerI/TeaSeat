// MUI-компоненты карточки: контейнер, чип, карточка, текст.
import { Box, Chip, Paper, Typography } from "@mui/material";
// Иконка предупреждения о проблемах.
import WarningAmberRoundedIcon from "@mui/icons-material/WarningAmberRounded";
// Тип заказа менеджера.
import type { ManagerOrder } from "../../../manager/types";
// Форматирование: канал, дата, деньги.
import { channelLabel, dateTime, money } from "../../../manager/format";
// Чип статуса заказа.
import { ManagerStatusChip } from "../ManagerStatusChip";
// CSS-модуль общих стилей.
import styles from "../../../scss/pages/ManagerShared.module.scss";

// Карточка заказа в списке; клик открывает детальную страницу.
export function ManagerOrderCard({ order, onOpen }: { order: ManagerOrder; onOpen: () => void }) {
  const location = order.delivery.warehouse?.name ?? "Точка не указана"; // склад отправки для отображения
  return (
    <Paper className={styles.card} tabIndex={0} role="button" onClick={onOpen} onKeyDown={(e) => { if (e.key === "Enter") onOpen(); }}>
      {/* Верхняя часть: номер, дата и место, чип статуса. */}
      <Box className={styles.cardHeader}>
        <Box>
          <Typography className={styles.cardTitle}>{order.order_number}</Typography> {/* номер заказа */}
          <Typography className={styles.meta}>{dateTime(order.timestamps.created_at)} · {location}</Typography> {/* дата и склад */}
        </Box>
        <ManagerStatusChip status={order.status} label={order.status_name} /> {/* статус */}
      </Box>
      {/* Нижний ряд метаданных: канал, клиент, проблемы, сумма. */}
      <Box className={styles.metricRow} sx={{ mt: 2, flexWrap: "wrap" }}>
        <Chip size="small" variant="outlined" label={channelLabel[order.sales_channel] ?? order.sales_channel} /> {/* канал продаж */}
        {order.customer?.name && <Chip size="small" variant="outlined" label={order.customer.name} />} {/* клиент */}
        {/* Бейдж с количеством открытых проблем. */}
        {order.fulfillment_summary.open_issues_count > 0 && (
          <Chip size="small" color="error" icon={<WarningAmberRoundedIcon />} label={`Проблем: ${order.fulfillment_summary.open_issues_count}`} />
        )}
        <Typography sx={{ ml: "auto", fontWeight: 800 }}>{money(order.totals.final_total)}</Typography> {/* итоговая сумма */}
      </Box>
    </Paper>
  );
}
