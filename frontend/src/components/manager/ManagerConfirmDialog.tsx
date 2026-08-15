// React-хук локального состояния.
import { useState } from "react";
// MUI-компоненты диалога.
import { Alert, Button, Dialog, DialogActions, DialogContent, DialogTitle } from "@mui/material";
// Тип и преобразование ошибок API.
import { StaffApiError, toStaffApiError } from "../../staff/errors";
// Хук отсчёта ожидания после 429.
import { useThrottleDeadline } from "../../staff/useThrottleDeadline";

// Простой диалог подтверждения опасных/важных команд (взять, вернуть, закрыть).
export function ManagerConfirmDialog({
  open, // открыт ли диалог
  title, // заголовок
  description, // описание последствий
  confirmLabel, // подпись кнопки подтверждения
  danger = false, // опасное действие (красная кнопка)
  onClose, // отмена
  onConfirm, // подтверждение (асинхронная команда)
}: {
  open: boolean;
  title: string;
  description: string;
  confirmLabel: string;
  danger?: boolean;
  onClose: () => void;
  onConfirm: () => Promise<void>;
}) {
  const [pending, setPending] = useState(false); // выполняется ли команда
  const [error, setError] = useState<StaffApiError | null>(null); // ошибка команды
  const throttle = useThrottleDeadline(error?.retryAfterMs); // таймер ожидания после 429

  // Отправка команды: блокирует интерфейс на время запроса.
  const submit = async () => {
    setPending(true); // включаем индикатор выполнения
    setError(null); // сбрасываем прошлую ошибку
    try {
      await onConfirm(); // выполняем команду
      onClose(); // успех — закрываем диалог
    } catch (reason) {
      // Приводим любую ошибку к StaffApiError и показываем в диалоге.
      setError(reason instanceof StaffApiError ? reason : toStaffApiError(reason));
    } finally {
      setPending(false); // выключаем индикатор
    }
  };

  return (
    <Dialog open={open} onClose={pending ? undefined : onClose} fullWidth maxWidth="xs">
      <DialogTitle>{title}</DialogTitle> {/* заголовок */}
      <DialogContent>
        {/* Предупреждение об опасности действия. */}
        <Alert severity={danger ? "warning" : "info"}>{description}</Alert>
        {/* Ошибка команды с таймером ожидания повтора. */}
        {error && <Alert severity="error" sx={{ mt: 2 }}>{error.message}{throttle.throttled && ` Повтор через ${throttle.remainingSeconds} сек.`}</Alert>}
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={pending}>Отмена</Button> {/* кнопка отмены */}
        <Button variant="contained" color={danger ? "error" : "primary"} disabled={pending || throttle.throttled} onClick={() => void submit()}>
          {pending ? "Выполняем…" : confirmLabel} {/* индикатор выполнения или подпись */}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
