import { useState } from "react";
import {
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  TextField,
} from "@mui/material";
import type {
  CourierDelivery,
  DeliveryCommand,
  DeliveryCommandState,
} from "../../courier/types";
import styles from "../../scss/pages/CourierShared.module.scss";

type PendingDialog = Extract<DeliveryCommand, "release" | "start" | "deliver"> | null;

export function DeliveryActions({
  delivery,
  command,
  online,
  onRelease,
  onStart,
  onDeliver,
}: {
  delivery: CourierDelivery;
  command?: DeliveryCommandState;
  online: boolean;
  onRelease: (reason: string) => Promise<unknown>;
  onStart: () => Promise<unknown>;
  onDeliver: () => Promise<unknown>;
}) {
  const [dialog, setDialog] = useState<PendingDialog>(null);
  const [reason, setReason] = useState("");
  const [fieldError, setFieldError] = useState("");
  const pending = command?.phase === "pending";

  const close = () => {
    if (!pending) {
      setDialog(null);
      setReason("");
      setFieldError("");
    }
  };

  const confirm = async () => {
    if (dialog === "release" && !reason.trim()) {
      setFieldError("Укажите причину возврата");
      return;
    }
    try {
      if (dialog === "release") await onRelease(reason);
      if (dialog === "start") await onStart();
      if (dialog === "deliver") await onDeliver();
      close();
    } catch {
      // Глобальное сообщение уже выставляет CourierProvider.
    }
  };

  return (
    <>
      <Box className={styles.stickyActions}>
        {delivery.actions.can_release && (
          <Button variant="outlined" color="secondary" onClick={() => setDialog("release")}>
            Вернуть
          </Button>
        )}
        {delivery.actions.can_start && (
          <Button variant="contained" onClick={() => setDialog("start")}>
            Начать доставку
          </Button>
        )}
        {delivery.actions.can_deliver && (
          <Button variant="contained" color="success" onClick={() => setDialog("deliver")}>
            Доставлено
          </Button>
        )}
      </Box>
      <Dialog open={dialog !== null} onClose={close} fullWidth maxWidth="xs">
        <DialogTitle>
          {dialog === "release" && "Вернуть доставку?"}
          {dialog === "start" && "Начать доставку?"}
          {dialog === "deliver" && "Подтвердить вручение?"}
        </DialogTitle>
        <DialogContent>
          {dialog === "release" ? (
            <TextField
              autoFocus
              fullWidth
              multiline
              minRows={3}
              value={reason}
              onChange={(event) => {
                setReason(event.target.value);
                setFieldError("");
              }}
              label="Причина возврата"
              error={Boolean(fieldError)}
              helperText={fieldError}
              inputProps={{ maxLength: 1000 }}
            />
          ) : (
            <DialogContentText>
              {dialog === "start"
                ? "После начала вернуть заказ самостоятельно уже нельзя."
                : delivery.delivery_kind === "transfer"
                  ? "Трансфер перейдёт в ожидание приёмки складом."
                  : "Заказ будет отмечен как доставленный клиенту."}
            </DialogContentText>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={close} disabled={pending}>Отмена</Button>
          <Button onClick={() => void confirm()} disabled={pending || !online} variant="contained">
            {pending ? "Сохраняем..." : "Подтвердить"}
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
}
