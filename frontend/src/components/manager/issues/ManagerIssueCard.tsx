// MUI-компоненты карточки: контейнер, чип, карточка, текст.
import { Box, Chip, Paper, Typography } from "@mui/material";
// Тип проблемы комплектации.
import type { FulfillmentIssue } from "../../../manager/types";
// Форматирование даты.
import { dateTime } from "../../../manager/format";
// Чип статуса.
import { ManagerStatusChip } from "../ManagerStatusChip";
// CSS-модуль общих стилей.
import styles from "../../../scss/pages/ManagerShared.module.scss";

// Карточка проблемы комплектации в списке; клик открывает детали.
export function ManagerIssueCard({ issue, onOpen }: { issue: FulfillmentIssue; onOpen: () => void }) {
  return (
    <Paper className={styles.card} role="button" tabIndex={0} onClick={onOpen} onKeyDown={(e) => { if (e.key === "Enter") onOpen(); }}>
      {/* Заголовок: товар, точка и дата, чип статуса. */}
      <Box className={styles.cardHeader}>
        <Box><Typography className={styles.cardTitle}>{issue.product?.name ?? `Товар #${issue.product_id}`}</Typography><Typography className={styles.meta}>{issue.warehouse?.name ?? `Точка #${issue.warehouse_id}`} · {dateTime(issue.created_at)}</Typography></Box>
        <ManagerStatusChip status={issue.status} label={issue.status_name} /> {/* статус */}
      </Box>
      {/* Описание причины. */}
      <Typography sx={{ mt: 1 }}>{issue.reason_message}</Typography>
      {/* Цифры: дефицит, резервы, ответственный менеджер. */}
      <Box className={styles.metricRow} sx={{ mt: 2, flexWrap: "wrap" }}>
        <Chip size="small" color="error" variant="outlined" label={`Дефицит: ${issue.shortage_quantity}`} /> {/* объём нехватки */}
        <Chip size="small" variant="outlined" label={`Online reserve: ${issue.reserved_online_before}`} /> {/* онлайн-резерв */}
        {issue.manager && <Chip size="small" variant="outlined" label={`Менеджер: ${issue.manager.name}`} />} {/* ответственный */}
      </Box>
    </Paper>
  );
}
