// React-хуки: useEffect — сброс формы при открытии, useState — поля.
import { useEffect, useState } from "react";
// MUI-компоненты диалога и полей.
import { Alert, Button, Dialog, DialogActions, DialogContent, DialogTitle, TextField } from "@mui/material";
// Ошибки API: тип, ошибка по полю, приведение.
import { StaffApiError, fieldError, toStaffApiError } from "../../../staff/errors";
// Хук отсчёта ожидания после 429.
import { useThrottleDeadline } from "../../../staff/useThrottleDeadline";
// Генерация UUID операции (идемпотентность).
import { newOperationId } from "../../../manager/api";

// Режимы диалога: добавить / изменить количество / заменить товар.
export type ItemDialogMode = "add" | "quantity" | "replace";

// Диалог правки позиции заказа (добавление, количество, замена).
export function ManagerItemDialog({
  open, // открыт ли диалог
  mode, // режим работы
  initialQuantity = 1, // текущее количество (для предзаполнения)
  onClose, // закрыть
  onSubmit, // отправить команду с данными формы
}: {
  open: boolean;
  mode: ItemDialogMode;
  initialQuantity?: number;
  onClose: () => void;
  onSubmit: (payload: { operation_id: string; product_id?: number; quantity: number; reason: string }) => Promise<void>;
}) {
  const [operationId, setOperationId] = useState(newOperationId); // UUID операции
  const [productId, setProductId] = useState(""); // ID товара
  const [quantity, setQuantity] = useState(String(initialQuantity)); // количество
  const [reason, setReason] = useState(""); // причина изменения
  const [pending, setPending] = useState(false); // выполняется ли запрос
  const [error, setError] = useState<StaffApiError | null>(null); // ошибка команды
  const throttle = useThrottleDeadline(error?.retryAfterMs); // таймер ожидания 429

  // При каждом открытии сбрасываем форму и генерируем новый operation_id.
  useEffect(() => {
    if (!open) return; // только при открытии
    setOperationId(newOperationId()); // новый UUID (защита от дублей)
    setProductId(""); // очищаем поле товара
    setQuantity(String(initialQuantity)); // восстанавливаем количество
    setReason(""); // очищаем причину
    setError(null); // убираем ошибку
  }, [initialQuantity, open]);

  // Отправка команды.
  const submit = async () => {
    setPending(true); // блокируем кнопки
    setError(null); // сбрасываем ошибку
    try {
      await onSubmit({
        operation_id: operationId, // UUID операции
        product_id: mode === "quantity" ? undefined : Number(productId), // товар не нужен при смене количества
        quantity: Number(quantity), // количество
        reason: reason.trim(), // причина (обрезаем пробелы)
      });
      onClose(); // успех — закрываем
    } catch (cause) {
      setError(cause instanceof StaffApiError ? cause : toStaffApiError(cause)); // показываем ошибку
    } finally {
      setPending(false); // разблокируем кнопки
    }
  };

  const title = mode === "add" ? "Добавить товар" : mode === "replace" ? "Заменить товар" : "Изменить количество"; // заголовок по режиму
  const productError = fieldError(error, "product_id"); // ошибка по полю товара (422)
  const quantityError = fieldError(error, "quantity"); // ошибка по количеству
  const reasonError = fieldError(error, "reason"); // ошибка по причине
  const hasVisibleFieldError = Boolean(productError || quantityError || reasonError); // есть ли ошибки у полей
  return (
    <Dialog open={open} onClose={pending ? undefined : onClose} fullWidth maxWidth="sm">
      <DialogTitle>{title}</DialogTitle> {/* заголовок */}
      <DialogContent sx={{ display: "flex", flexDirection: "column", gap: 2, pt: "8px !important" }}>
        {/* Временное предупреждение для режимов с выбором товара. */}
        {mode !== "quantity" && <Alert severity="warning">Пока backend не отдаёт warehouse-scoped поиск кандидатов. В первой версии используется ID товара; окончательное наличие всё равно проверит команда.</Alert>}
        {/* Общая ошибка, если она не привязана к полю. */}
        {error && (error.kind !== "validation" || !hasVisibleFieldError) && <Alert severity="error">{error.message}{throttle.throttled && ` Повтор через ${throttle.remainingSeconds} сек.`}</Alert>}
        {/* Поле ID товара (не для режима изменения количества). */}
        {mode !== "quantity" && <TextField type="number" label="ID товара" value={productId} onChange={(e) => setProductId(e.target.value)} error={Boolean(productError)} helperText={productError} />}
        {/* Поле количества. */}
        <TextField type="number" label="Количество в единицах stock_unit" value={quantity} onChange={(e) => setQuantity(e.target.value)} error={Boolean(quantityError)} helperText={quantityError} />
        {/* Поле причины с ограничением длины. */}
        <TextField multiline minRows={3} label="Причина изменения" value={reason} onChange={(e) => setReason(e.target.value)} error={Boolean(reasonError)} helperText={reasonError ?? `${reason.length}/1000`} inputProps={{ maxLength: 1000 }} />
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={pending}>Отмена</Button> {/* отмена */}
        {/* Сохранение заблокировано при пустой причине/количестве/товаре. */}
        <Button variant="contained" onClick={() => void submit()} disabled={pending || throttle.throttled || !reason.trim() || !Number(quantity) || (mode !== "quantity" && !Number(productId))}>Сохранить</Button>
      </DialogActions>
    </Dialog>
  );
}
