// MUI-компоненты состояний: баннер, контейнер, кнопка, карточка, скелетон, текст.
import { Alert, Box, Button, Paper, Skeleton, Typography } from "@mui/material";
// Иконка ошибки.
import ErrorOutlineRoundedIcon from "@mui/icons-material/ErrorOutlineRounded";
// Иконка пустого списка (входящие/коробка).
import InboxRoundedIcon from "@mui/icons-material/InboxRounded";
// Навигация (для кнопки «Войти» при истёкшей сессии).
import { useNavigate } from "react-router-dom";
// Тип ошибки API кабинета.
import type { StaffApiError } from "../../staff/errors";
// CSS-модуль общих стилей.
import styles from "../../scss/pages/ManagerShared.module.scss";

// Скелетон списка на время первичной загрузки (placeholder-карточки).
export function ManagerListSkeleton({ rows = 4 }: { rows?: number }) {
  return <Box className={styles.list}>{Array.from({ length: rows }, (_, i) => (
    <Skeleton key={i} height={150} variant="rounded" animation="wave" /> // «пульсирующая» карточка
  ))}</Box>;
}

// Пустое состояние: список существует, но в нём нет элементов.
export function ManagerEmptyState({ title, description }: { title: string; description: string }) {
  return (
    <Paper className={styles.emptyState}>
      <InboxRoundedIcon className={styles.emptyIcon} /> {/* иконка пустого списка */}
      <Typography className={styles.emptyTitle}>{title}</Typography> {/* заголовок */}
      <Typography className={styles.muted}>{description}</Typography> {/* пояснение */}
    </Paper>
  );
}

// Состояние ошибки: сообщение + код + действие (войти или повторить).
export function ManagerErrorState({ error, onRetry }: { error: StaffApiError; onRetry: () => void }) {
  const navigate = useNavigate(); // переход на страницу входа
  return (
    <Alert
      severity="error"
      icon={<ErrorOutlineRoundedIcon />} // иконка ошибки
      action={
        // Если сессия истекла — предлагаем войти заново.
        error.kind === "unauthorized"
          ? <Button color="inherit" onClick={() => navigate("/login")}>Войти</Button>
          : error.retryable && <Button color="inherit" onClick={onRetry}>Повторить</Button> // иначе — повтор, если это допустимо
      }
    >
      <strong>{error.message}</strong> {/* текст ошибки */}
      {error.code && <Typography component="div" className={styles.errorCode}>Код: {error.code}</Typography>} {/* машинный код */}
    </Alert>
  );
}
