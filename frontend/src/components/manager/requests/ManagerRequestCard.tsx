// MUI-компоненты карточки: контейнер, чип, карточка, текст.
import { Box, Chip, Paper, Typography } from "@mui/material";
// Тип обращения клиента.
import type { ManagerOrderRequest } from "../../../manager/types";
// Форматирование: дата и название типа обращения.
import { dateTime, requestTypeLabel } from "../../../manager/format";
// Чип статуса.
import { ManagerStatusChip } from "../ManagerStatusChip";
// CSS-модуль общих стилей.
import styles from "../../../scss/pages/ManagerShared.module.scss";

// Карточка обращения клиента; клик открывает детали.
export function ManagerRequestCard({ item, onOpen }: { item: ManagerOrderRequest; onOpen: () => void }) {
  return (
    <Paper className={styles.card} role="button" tabIndex={0} onClick={onOpen} onKeyDown={(e) => { if (e.key === "Enter") onOpen(); }}>
      {/* Заголовок: тип обращения, связанный заказ и дата, статус. */}
      <Box className={styles.cardHeader}>
        <Box><Typography className={styles.cardTitle}>{requestTypeLabel[item.type] ?? item.type}</Typography><Typography className={styles.meta}>{item.order?.order_number ?? `Заказ #${item.order_id}`} · {dateTime(item.created_at)}</Typography></Box>
        <ManagerStatusChip status={item.status} /> {/* статус обращения */}
      </Box>
      {/* Текст обращения клиента. */}
      <Typography className={styles.preWrap} sx={{ mt: 1 }}>{item.message || "Клиент не оставил комментарий"}</Typography>
      {/* Клиент и ответственный менеджер. */}
      <Box className={styles.metricRow} sx={{ mt: 2, flexWrap: "wrap" }}>
        {item.customer?.name && <Chip size="small" variant="outlined" label={item.customer.name} />} {/* клиент */}
        {item.manager?.name && <Chip size="small" variant="outlined" label={`Менеджер: ${item.manager.name}`} />} {/* менеджер */}
      </Box>
    </Paper>
  );
}
