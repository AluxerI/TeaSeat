// React-хуки: useEffect — сброс формы при открытии, useState — поля формы.
import { useEffect, useState } from "react";
// MUI-компоненты диалога и полей.
import {
  Alert, // баннер описания/ошибки
  Button, // кнопки
  Dialog, // окно
  DialogActions, // нижняя панель кнопок
  DialogContent, // тело
  DialogTitle, // заголовок
  MenuItem, // пункты селекта
  TextField, // текстовое поле
} from "@mui/material";
// Ошибки: тип, ошибка по полю, приведение.
import { StaffApiError, fieldError, toStaffApiError } from "../../staff/errors";
// Хук отсчёта ожидания после 429.
import { useThrottleDeadline } from "../../staff/useThrottleDeadline";

// Конфигурация выпадающего списка в диалоге.
interface SelectField {
  name: string; // имя поля в отправляемых данных
  label: string; // подпись поля
  options: Array<{ value: string; label: string }>; // варианты
}

// Пропсы универсального диалога команд.
interface Props {
  open: boolean; // открыт ли диалог
  title: string; // заголовок
  description?: string; // пояснение
  fieldName?: string; // имя текстового поля
  fieldLabel?: string; // подпись текстового поля
  initialValue?: string; // предзаполненное значение
  maxLength?: number; // максимальная длина текста
  confirmLabel: string; // подпись кнопки подтверждения
  danger?: boolean; // опасное действие
  select?: SelectField; // опциональный выпадающий список
  onClose: () => void; // отмена
  onSubmit: (values: Record<string, string>) => Promise<void>; // команда с данными формы
}

/** Универсальный диалог команд. Его ключевая задача — не оформление, а единая
 * семантика ошибок: 422 остаётся возле поля, 409 показывает конфликт внутри
 * формы, 429 блокирует повтор ровно на время из HTTP-заголовка. */
export function ManagerActionDialog({
  open,
  title,
  description,
  fieldName = "comment", // имя поля по умолчанию
  fieldLabel = "Комментарий", // подпись поля по умолчанию
  initialValue = "", // начальное значение
  maxLength = 2000, // лимит по умолчанию
  confirmLabel,
  danger = false,
  select,
  onClose,
  onSubmit,
}: Props) {
  const [value, setValue] = useState(initialValue); // текст поля
  const [selectValue, setSelectValue] = useState(""); // выбранный вариант селекта
  const [pending, setPending] = useState(false); // выполняется ли команда
  const [error, setError] = useState<StaffApiError | null>(null); // ошибка команды
  const throttle = useThrottleDeadline(error?.retryAfterMs); // таймер ожидания 429

  // При каждом открытии сбрасываем форму к начальным значениям.
  useEffect(() => {
    if (!open) return; // пересоздаём только при открытии
    setValue(initialValue); // текст поля
    setSelectValue(""); // селект сбрасываем в первый вариант
    setError(null); // убираем старую ошибку
  }, [initialValue, open]);

  // Отправка команды с данными формы.
  const submit = async () => {
    setPending(true); // блокируем кнопки
    setError(null); // сбрасываем ошибку
    try {
      const values: Record<string, string> = { [fieldName]: value.trim() }; // собираем значение текста
      if (select) values[select.name] = selectValue; // и значение селекта
      await onSubmit(values); // выполняем команду
      onClose(); // успех — закрываем
    } catch (reason) {
      setError(reason instanceof StaffApiError ? reason : toStaffApiError(reason)); // показываем ошибку
    } finally {
      setPending(false); // разблокируем кнопки
    }
  };

  const valueError = fieldError(error, fieldName); // ошибка по текстовому полю (422)
  const selectError = select ? fieldError(error, select.name) : undefined; // ошибка по селекту
  const hasVisibleFieldError = Boolean(valueError || selectError); // есть ли ошибки у полей
  const empty = !value.trim() || Boolean(select && !selectValue); // не заполнена ли форма

  return (
    <Dialog open={open} onClose={pending ? undefined : onClose} fullWidth maxWidth="sm">
      <DialogTitle>{title}</DialogTitle> {/* заголовок */}
      <DialogContent sx={{ display: "flex", flexDirection: "column", gap: 2, pt: "8px !important" }}>
        {description && <Alert severity="info">{description}</Alert>} {/* пояснение */}
        {/* Общая ошибка, если её нельзя показать возле конкретного поля. */}
        {error && (error.kind !== "validation" || !hasVisibleFieldError) && (
          <Alert severity={error.kind === "throttled" ? "warning" : "error"}>
            {error.message}
            {throttle.throttled && ` Повтор через ${throttle.remainingSeconds} сек.`} {/* таймер ожидания */}
          </Alert>
        )}
        {/* Выпадающий список (если задан). */}
        {select && (
          <TextField
            select
            label={select.label} // подпись
            value={selectValue} // выбранное значение
            onChange={(event) => setSelectValue(event.target.value)} // выбор
            error={Boolean(selectError)} // подсветка ошибки
            helperText={selectError} // текст ошибки
          >
            {select.options.map((option) => <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>)} {/* варианты */}
          </TextField>
        )}
        {/* Текстовое поле (многострочное). */}
        <TextField
          autoFocus={!select} // автофокус, если нет селекта
          multiline
          minRows={3} // минимальная высота
          label={fieldLabel} // подпись
          value={value} // значение
          onChange={(event) => setValue(event.target.value)} // ввод
          error={Boolean(valueError)} // подсветка ошибки
          helperText={valueError ?? `${value.length}/${maxLength}`} // ошибка или счётчик символов
          inputProps={{ maxLength }} // ограничение длины
        />
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={pending}>Отмена</Button> {/* отмена */}
        <Button
          variant="contained"
          color={danger ? "error" : "primary"} // красная кнопка для опасных действий
          onClick={() => void submit()}
          disabled={pending || empty || throttle.throttled} // блокировка во время запроса/пустой формы/ожидания 429
        >
          {pending ? "Сохраняем…" : confirmLabel} {/* индикатор или подпись */}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
