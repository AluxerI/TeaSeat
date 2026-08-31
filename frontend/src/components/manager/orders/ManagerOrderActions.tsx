// React-хук: useState — выбранный диалог.
import { useState } from "react";
// MUI-компоненты: баннер, контейнер, кнопка, подсказка.
import { Alert, Box, Button, Tooltip } from "@mui/material";
// Иконки кнопок действий.
import CheckRoundedIcon from "@mui/icons-material/CheckRounded"; // подтвердить
import CloseRoundedIcon from "@mui/icons-material/CloseRounded"; // отменить
import NoteAddRoundedIcon from "@mui/icons-material/NoteAddRounded"; // заметка
import InventoryRoundedIcon from "@mui/icons-material/InventoryRounded"; // возврат на склад
import LocalShippingRoundedIcon from "@mui/icons-material/LocalShippingRounded"; // назначить курьера
import EventRoundedIcon from "@mui/icons-material/EventRounded"; // перенос доставки
// API-команды над заказом.
import {
  addManagerOrderNote, // добавить заметку
  cancelManagerOrder, // отменить заказ
  confirmManagerOrder, // подтвердить заказ
  returnManagerOrderToStock, // вернуть товар на склад
} from "../../../manager/api";
// Тип заказа.
import type { ManagerOrder } from "../../../manager/types";
// Контекст менеджера (интернет, тост, счётчики).
import { useManager } from "../../../contexts/ManagerContext";
// Диалог с полем (заметка/отмена/возврат).
import { ManagerActionDialog } from "../ManagerActionDialog";
// Простой диалог подтверждения.
import { ManagerConfirmDialog } from "../ManagerConfirmDialog";
// CSS-модуль общих стилей.
import styles from "../../../scss/pages/ManagerShared.module.scss";

// Какой диалог открыт.
type DialogKind = "note" | "cancel" | "confirm" | "return" | null;

// Панель действий менеджера над заказом.
export function ManagerOrderActions({ order, onChanged }: { order: ManagerOrder; onChanged: () => Promise<unknown> }) {
  const { online, notify, refreshCounters } = useManager(); // интернет, тост, счётчики
  const [dialog, setDialog] = useState<DialogKind>(null); // открытый диалог

  // Общий обработчик успеха команды: тост + обновление заказа и счётчиков.
  const success = async (message: string) => {
    notify(message); // показываем сообщение сервера
    await Promise.all([onChanged(), refreshCounters()]); // обновляем данные
  };

  return (
    <>
      <Box className={styles.actions}>
        {/* Кнопки доступны только при соответствующих правах; офлайн блокирует. */}
        {order.actions.can_confirm && <Button variant="contained" startIcon={<CheckRoundedIcon />} disabled={!online} onClick={() => setDialog("confirm")}>Подтвердить</Button>}
        {order.actions.can_cancel && <Button color="error" startIcon={<CloseRoundedIcon />} disabled={!online} onClick={() => setDialog("cancel")}>Отменить</Button>}
        {order.actions.can_add_internal_note && <Button startIcon={<NoteAddRoundedIcon />} disabled={!online} onClick={() => setDialog("note")}>Добавить заметку</Button>}
        {order.status === "ready_for_delivery" && <Button startIcon={<InventoryRoundedIcon />} disabled={!online} onClick={() => setDialog("return")}>Вернуть на склад</Button>}
        {/* Перенос доставки: временно отключён (backend не даёт интервалы). */}
        {order.actions.can_reschedule && (
          <Tooltip title="Backend пока не даёт Manager список допустимых интервалов для адреса клиента">
            <span><Button startIcon={<EventRoundedIcon />} disabled>Перенести доставку</Button></span>
          </Tooltip>
        )}
        {/* Назначение курьера: временно отключён (нет списка курьеров). */}
        {order.status === "ready_for_delivery" && (
          <Tooltip title="Backend принимает courier_id, но не даёт Manager scoped-список доступных курьеров">
            <span><Button startIcon={<LocalShippingRoundedIcon />} disabled>Назначить курьера</Button></span>
          </Tooltip>
        )}
      </Box>
      {/* Предупреждение, если менеджеру назначены не все точки заказа. */}
      {!order.manager_access.all_locations_assigned && (
        <Alert severity="warning">Заказ затрагивает точки, которые вам не назначены. Общие команды отключены backend.</Alert>
      )}

      {/* Диалог подтверждения заказа. */}
      <ManagerConfirmDialog
        open={dialog === "confirm"} // открыт при выборе подтверждения
        title="Подтвердить заказ?"
        description="После подтверждения заказ станет доступен сборщику. Backend повторно проверит резервы и открытые проблемы."
        confirmLabel="Подтвердить"
        onClose={() => setDialog(null)} // отмена
        onConfirm={async () => { const result = await confirmManagerOrder(order.id); await success(result.message); }} // подтверждение
      />
      {/* Диалог добавления внутренней заметки. */}
      <ManagerActionDialog
        open={dialog === "note"}
        title="Внутренняя заметка"
        description="Заметка доступна сотрудникам и не изменяет статус заказа."
        fieldName="comment"
        fieldLabel="Комментарий"
        confirmLabel="Добавить"
        onClose={() => setDialog(null)}
        onSubmit={async ({ comment }) => { const result = await addManagerOrderNote(order.id, comment); await success(result.message); }}
      />
      {/* Диалог отмены заказа с причиной. */}
      <ManagerActionDialog
        open={dialog === "cancel"}
        title="Отмена заказа"
        description="Backend освободит только ещё не списанные резервы. Оплаченный или уже собираемый заказ отменить нельзя."
        fieldName="reason"
        fieldLabel="Причина отмены"
        maxLength={1000}
        confirmLabel="Отменить заказ"
        danger // опасное действие
        onClose={() => setDialog(null)}
        onSubmit={async ({ reason }) => { const result = await cancelManagerOrder(order.id, reason); await success(result.message); }}
      />
      {/* Диалог возврата упакованного заказа на склад. */}
      <ManagerActionDialog
        open={dialog === "return"}
        title="Разобрать упаковку"
        description="Товар вернётся в складской резерв, а заказ снова потребует сборки."
        fieldName="reason"
        fieldLabel="Причина возврата"
        maxLength={1000}
        confirmLabel="Вернуть на склад"
        danger
        onClose={() => setDialog(null)}
        onSubmit={async ({ reason }) => { const result = await returnManagerOrderToStock(order.id, reason); await success(result.message); }}
      />
    </>
  );
}
