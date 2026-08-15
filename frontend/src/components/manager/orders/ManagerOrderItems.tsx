// React-хуки: useRef — стабильный operation_id для удаления, useState — диалоги.
import { useRef, useState } from "react";
// MUI-компоненты: контейнер, кнопка, разделитель, карточка, текст.
import { Box, Button, Divider, Paper, Typography } from "@mui/material";
// Иконки действий с позициями.
import AddRoundedIcon from "@mui/icons-material/AddRounded"; // добавить
import DeleteOutlineRoundedIcon from "@mui/icons-material/DeleteOutlineRounded"; // удалить
import EditRoundedIcon from "@mui/icons-material/EditRounded"; // изменить количество
import SwapHorizRoundedIcon from "@mui/icons-material/SwapHorizRounded"; // заменить
// API-команды изменения состава заказа.
import {
  addManagerOrderItem, // добавить позицию
  changeManagerOrderItem, // изменить количество
  newOperationId, // новый UUID операции
  removeManagerOrderGift, // удалить набор
  removeManagerOrderItem, // удалить позицию
  replaceManagerOrderItem, // заменить позицию
} from "../../../manager/api";
// Форматирование денег.
import { money } from "../../../manager/format";
// Типы заказа и позиции.
import type { ManagerOrder, ManagerOrderItem } from "../../../manager/types";
// Контекст менеджера.
import { useManager } from "../../../contexts/ManagerContext";
// Диалог с полем (для удаления с причиной).
import { ManagerActionDialog } from "../ManagerActionDialog";
// Диалог добавления/правки позиции.
import { ManagerItemDialog, type ItemDialogMode } from "./ManagerItemDialog";
// CSS-модуль общих стилей.
import styles from "../../../scss/pages/ManagerShared.module.scss";

// Состояние диалога позиции: режим + необязательная текущая позиция.
interface ItemDialogState { mode: ItemDialogMode; item?: ManagerOrderItem }

// Блок состава заказа: список позиций и наборов, добавление/правка/удаление.
export function ManagerOrderItems({ order, onChanged }: { order: ManagerOrder; onChanged: () => Promise<unknown> }) {
  const { online, notify, refreshCounters } = useManager(); // интернет, тост, счётчики
  const [itemDialog, setItemDialog] = useState<ItemDialogState | null>(null); // диалог позиции
  // Состояние удаления: тип (позиция/набор), id и имя для заголовка.
  const [remove, setRemove] = useState<{ type: "item" | "gift"; id: number; name: string } | null>(null);
  const removeOperationId = useRef(newOperationId()); // UUID операции удаления

  // Общий обработчик успеха: тост + обновление заказа и счётчиков.
  const changed = async (message: string) => {
    notify(message); // сообщение сервера
    await Promise.all([onChanged(), refreshCounters()]); // обновляем данные
  };

  // Открытие диалога удаления: генерируем свежий operation_id.
  const openRemove = (target: NonNullable<typeof remove>) => {
    removeOperationId.current = newOperationId(); // новый UUID (защита от дублей)
    setRemove(target); // открываем диалог
  };

  return (
    <Paper className={styles.panel}>
      {/* Заголовок панели и кнопка добавления товара. */}
      <Box className={styles.cardHeader}>
        <Typography className={styles.sectionTitle}>Состав заказа</Typography>
        {order.actions.can_modify_items && <Button size="small" startIcon={<AddRoundedIcon />} disabled={!online} onClick={() => setItemDialog({ mode: "add" })}>Добавить товар</Button>}
      </Box>
      <Box className={styles.stack} sx={{ mt: 1 }}>
        {/* Обычные позиции (не входящие в набор). */}
        {(order.items ?? []).filter((item) => !item.order_gift_id).map((item) => (
          <Box key={item.id} className={`${styles.row} ${styles.dividerRow}`}>
            <Box>
              <Typography sx={{ fontWeight: 750 }}>{item.product?.name ?? `Позиция #${item.id}`}</Typography> {/* название товара */}
              <Typography className={styles.meta}>{item.quantity} {item.stock_unit} · {money(item.prices.total_price)}</Typography> {/* количество и цена */}
            </Box>
            {/* Действия над позицией (если разрешено редактирование). */}
            {order.actions.can_modify_items && (
              <Box className={styles.actions}>
                <Button size="small" startIcon={<EditRoundedIcon />} disabled={!online} onClick={() => setItemDialog({ mode: "quantity", item })}>Количество</Button>
                <Button size="small" startIcon={<SwapHorizRoundedIcon />} disabled={!online} onClick={() => setItemDialog({ mode: "replace", item })}>Заменить</Button>
                <Button size="small" color="error" startIcon={<DeleteOutlineRoundedIcon />} disabled={!online} onClick={() => openRemove({ type: "item", id: item.id, name: item.product?.name ?? "позицию" })}>Удалить</Button>
              </Box>
            )}
          </Box>
        ))}
        {/* Подарочные наборы в заказе. */}
        {(order.gifts ?? []).map((gift) => (
          <Box key={gift.id} className={`${styles.row} ${styles.dividerRow}`}>
            <Box>
              <Typography sx={{ fontWeight: 750 }}>Подарок: {gift.name}</Typography> {/* название набора */}
              <Typography className={styles.meta}>{gift.quantity} шт. · {money(gift.prices.total_price)}</Typography> {/* количество и цена */}
            </Box>
            {/* Удаление набора целиком. */}
            {order.actions.can_modify_items && <Button size="small" color="error" startIcon={<DeleteOutlineRoundedIcon />} disabled={!online} onClick={() => openRemove({ type: "gift", id: gift.id, name: gift.name })}>Удалить набор</Button>}
          </Box>
        ))}
      </Box>
      {/* Пояснение про идемпотентность операций. */}
      {order.actions.can_modify_items && <><Divider sx={{ my: 2 }} /><Typography className={styles.meta}>Каждая правка имеет UUID операции. Повтор после обрыва сети не применит изменение дважды.</Typography></>}

      {/* Диалог добавления/правки/замены позиции. */}
      {itemDialog && (
        <ManagerItemDialog
          open
          mode={itemDialog.mode} // режим: add/quantity/replace
          initialQuantity={itemDialog.item?.quantity ?? 1} // текущее количество
          onClose={() => setItemDialog(null)} // закрыть
          onSubmit={async (payload) => {
            const base = { operation_id: payload.operation_id, reason: payload.reason }; // общая часть команды
            // Выбираем команду по режиму: добавить / заменить / изменить количество.
            const result = itemDialog.mode === "add"
              ? await addManagerOrderItem(order.id, { ...base, product_id: payload.product_id!, quantity: payload.quantity })
              : itemDialog.mode === "replace"
                ? await replaceManagerOrderItem(order.id, itemDialog.item!.id, { ...base, product_id: payload.product_id!, quantity: payload.quantity })
                : await changeManagerOrderItem(order.id, itemDialog.item!.id, { ...base, quantity: payload.quantity });
            // Предупреждаем, если операция уже была применена ранее.
            await changed(result.message + (result.already_applied ? " (операция уже была применена)" : ""));
          }}
        />
      )}
      {/* Диалог удаления позиции/набора с указанием причины. */}
      <ManagerActionDialog
        open={Boolean(remove)}
        title={`Удалить ${remove?.name ?? "позицию"}?`}
        description="Backend пересчитает суммы и заново распределит резервы. Если товара на новом распределении не хватит, вся операция откатится."
        fieldName="reason"
        fieldLabel="Причина удаления"
        maxLength={1000}
        confirmLabel="Удалить"
        danger
        onClose={() => setRemove(null)} // отмена
        onSubmit={async ({ reason }) => {
          if (!remove) return; // без цели — не отправляем
          const payload = { operation_id: removeOperationId.current, reason }; // идемпотентная команда
          // Удаляем набор или позицию в зависимости от типа.
          const result = remove.type === "gift"
            ? await removeManagerOrderGift(order.id, remove.id, payload)
            : await removeManagerOrderItem(order.id, remove.id, payload);
          await changed(result.message + (result.already_applied ? " (операция уже была применена)" : ""));
        }}
      />
    </Paper>
  );
}
