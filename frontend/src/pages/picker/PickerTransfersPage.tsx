import { useState } from "react";
import { Box, Button, Chip, Paper, Typography } from "@mui/material";
import SwapHorizIcon from "@mui/icons-material/SwapHoriz";
import ArrowForwardIcon from "@mui/icons-material/ArrowForward";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import Inventory2Icon from "@mui/icons-material/Inventory2";
import HandymanIcon from "@mui/icons-material/Handyman";
import { usePageVisibility } from "../../hooks/usePageVisibility";
import { useRecursivePolling } from "../../hooks/useRecursivePolling";
import { usePicker } from "../../picker/PickerContext";
import type { PickerOrder } from "../../picker/types";
import styles from "../../scss/pages/PickerTransfers.module.scss";

/** Страница «Трансферы»: упаковки, которые курьер уже привёз на одну из
 *  назначенных сборщику точек. До прибытия они находятся у courier, поэтому
 *  здесь появятся только после статуса `awaiting_receipt`. */
export default function PickerTransfersPage() {
  const {
    receive,
    transfers,
    transfersLoading,
    transfersError,
    refreshTransfers,
    online,
  } = usePicker();
  const visiblePage = usePageVisibility();
  const [busyId, setBusyId] = useState<number | null>(null); // какой заказ сейчас принимаем
  const [commandError, setCommandError] = useState<string | null>(null);

  // В отличие от прежнего локального state, данные теперь переживают переходы
  // между страницами и сохраняются как read-only snapshot для offline-start.
  useRecursivePolling({
    enabled: online && visiblePage,
    intervalMs: 30_000,
    refresh: refreshTransfers,
  });

  // «Принять»: вызываем действие из контекста (оно дёргает API),
  // затем перезагружаем список — принятый трансфер из него исчезнет.
  const accept = async (orderId: number) => {
    setBusyId(orderId);
    setCommandError(null);
    try {
      await receive(orderId);
    } catch (err) {
      setCommandError(
        err instanceof Error ? err.message : "Не удалось принять трансфер"
      );
    } finally {
      setBusyId(null);
    }
  };

  return (
    <Box className={styles.page}>
      {/* Заголовок + кнопка «Обновить» */}
      <Box className={styles.header}>
        <Typography component="h1" className={styles.pageTitle}>
          Входящие трансферы
        </Typography>
        <Button
          variant="outlined"
          size="small"
          onClick={() => refreshTransfers()}
          disabled={transfersLoading || !online}
        >
          Обновить
        </Button>
      </Box>

      <Typography className={styles.pageSubtitle}>
        Здесь только уже прибывшие межскладские упаковки, ожидающие вашей приёмки
      </Typography>

      {(transfersError || commandError) && (
        <Typography className={styles.errorText}>
          {commandError ?? transfersError}
        </Typography>
      )}

      {/* Список трансферов; если пусто — подсказка */}
      <Box className={styles.orderList}>
        {!transfersLoading && transfers.length === 0 && (
          <Box className={styles.emptyWrap}>
            <SwapHorizIcon className={styles.emptyIcon} />
            <Typography className={styles.emptyText}>
              Входящих трансферов нет
            </Typography>
          </Box>
        )}
        {transfers.map((order) => (
          <TransferCard
            key={order.id}
            order={order}
            busy={busyId === order.id}
            online={online}
            onAccept={() => accept(order.id)}
          />
        ))}
      </Box>
    </Box>
  );
}

/** Карточка трансфера: откуда едет, что внутри, и кнопка «Принять». */
function TransferCard({
  order,
  busy,
  online,
  onAccept,
}: {
  order: PickerOrder;
  busy: boolean;
  online: boolean;
  onAccept: () => void;
}) {
  const giftCount = order.gifts.length;
  const itemCount = order.items.length;
  const from = order.warehouse?.name ?? "—"; // склад-отправитель
  const to = order.destination_warehouse?.name ?? "—"; // наш склад

  return (
    <Paper className={styles.orderCard} elevation={0}>
      {/* Номер задания и клиентский заказ */}
      <Box className={styles.orderHeader}>
        <Typography className={styles.orderNumber}>
          {order.order_number}
        </Typography>
        <Typography className={styles.customerOrder}>
          заказ № {order.customer_order_number}
        </Typography>
      </Box>

      {/* Тип работы и статус */}
      <Box className={styles.chipsRow}>
        <Chip
          size="small"
          className={styles.jobChip}
          icon={order.job_type === "source" ? <Inventory2Icon /> : <HandymanIcon />}
          label={order.job_type === "source" ? "Сборка" : "Объединение"}
          variant="outlined"
        />
        <Chip size="small" label={order.status_name} color="warning" />
      </Box>

      {/* Маршрут: с какого склада на наш */}
      <Box className={styles.route}>
        <Typography className={styles.routeText}>
          {from} <ArrowForwardIcon fontSize="inherit" /> {to ?? "—"}
        </Typography>
      </Box>

      {/* Подвал: краткий состав + кнопка «Принять» (если сервер разрешает) */}
      <Box className={styles.orderFooter}>
        <Typography className={styles.metaText}>
          {giftCount > 0
            ? `${giftCount} подарочн. · ${itemCount} поз.`
            : `${itemCount} поз.`}
        </Typography>
        {order.actions.can_receive && (
          <Button
            variant="contained"
            size="small"
            className={styles.receiveBtn}
            startIcon={<CheckCircleIcon />}
            onClick={onAccept}
            disabled={busy || !online}
          >
            {busy ? "Приём..." : "Принять"}
          </Button>
        )}
      </Box>
    </Paper>
  );
}
