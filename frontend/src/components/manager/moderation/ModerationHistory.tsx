// MUI-компоненты: контейнер, карточка, текст.
import { Box, Paper, Typography } from "@mui/material";
// Тип записи журнала модерации.
import type { ModerationLog } from "../../../manager/types";
// Форматирование: дата и подпись кода причины.
import { dateTime, moderationReasonLabel } from "../../../manager/format";
// CSS-модуль общих стилей.
import styles from "../../../scss/pages/ManagerShared.module.scss";

// История модерации: список действий модераторов над отзывом/оценкой.
export function ModerationHistory({ logs = [] }: { logs?: ModerationLog[] }) {
  return (
    <Paper className={styles.panel}>
      <Typography className={styles.sectionTitle}>История модерации</Typography>
      {/* Если записей нет — поясняем это. */}
      {!logs.length && <Typography className={styles.muted} sx={{ mt: 1 }}>Команд модерации ещё не было.</Typography>}
      {/* Каждая запись: действие, причина, комментарий, модератор и дата. */}
      {logs.map((log) => <Box key={log.id} className={styles.dividerRow}><Typography sx={{ fontWeight: 750 }}>{log.action} {log.reason_code && `· ${moderationReasonLabel[log.reason_code] ?? log.reason_code}`}</Typography><Typography>{log.comment}</Typography><Typography className={styles.meta}>{dateTime(log.created_at)} · {log.moderator.name}</Typography></Box>)}
    </Paper>
  );
}
