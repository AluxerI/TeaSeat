import { useCallback, useEffect, useState } from "react";
import { Box, Button, Chip, Paper, Typography } from "@mui/material";
import SwapHorizIcon from "@mui/icons-material/SwapHoriz";
import ArrowForwardIcon from "@mui/icons-material/ArrowForward";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import Inventory2Icon from "@mui/icons-material/Inventory2";
import HandymanIcon from "@mui/icons-material/Handyman";
import { fetchIncomingTransfers } from "../../picker/api";
import { usePicker } from "../../picker/PickerContext";
import type { PickerOrder } from "../../picker/types";
import styles from "../../scss/pages/PickerTransfers.module.scss";

/** Страница «Трансферы»: заказы, собранные на другом складе и едущие к нам.
 *  Сборщик принимает их кнопкой «Принять» — товар считается приехавшим.
 *  Список хранится локально на странице (в контекст он не входит). */
export default function PickerTransfersPage() {
  const { receive } = usePicker();
  const [transfers, setTransfers] = useState<PickerOrder[]>([]);
  const [fetching, setFetching] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null); // какой заказ сейчас принимаем

  // Загрузка списка входящих трансферов с сервера.
  const load = useCallback(async () => {
    setFetching(true);
    setError(null);
    try {
      const res = await fetchIncomingTransfers();
      setTransfers(res.data);
    } catch (err) {
      setError(
        err instanceof Error ? err.message : "Не удалось загрузить трансферы"
      );
    } finally {
      setFetching(false);
    }
  }, []);

  // При открытии страницы — сразу грузим список.
  useEffect(() => {
    load();
  }, [load]);

  // «Принять»: вызываем действие из контекста (оно дёргает API),
  // затем перезагружаем список — принятый трансфер из него исчезнет.
  const accept = async (orderId: number) => {
    setBusyId(orderId);
    setError(null);
    try {
      await receive(orderId);
      await load();
    } catch (err) {
      setError(
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
        <Button variant="outlined" size="small" onClick={load} disabled={fetching}>
          Обновить
        </Button>
      </Box>

      {error && <Typography className={styles.errorText}>{error}</Typography>}

      {/* Список трансферов; если пусто — подсказка */}
      <Box className={styles.orderList}>
        {!fetching && transfers.length === 0 && (
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
  onAccept,
}: {
  order: PickerOrder;
  busy: boolean;
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
            disabled={busy}
          >
            {busy ? "Приём..." : "Принять"}
          </Button>
        )}
      </Box>
    </Paper>
  );
}
