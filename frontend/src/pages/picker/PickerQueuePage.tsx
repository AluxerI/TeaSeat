import { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import {
  Box,
  Button,
  Chip,
  Paper,
  Tab,
  Tabs,
  Typography,
} from "@mui/material";
import ArrowForwardIcon from "@mui/icons-material/ArrowForward";
import Inventory2Icon from "@mui/icons-material/Inventory2";
import HandymanIcon from "@mui/icons-material/Handyman";
import { usePageVisibility } from "../../hooks/usePageVisibility";
import { useRecursivePolling } from "../../hooks/useRecursivePolling";
import { usePicker } from "../../picker/PickerContext";
import type { PickerJobType, PickerOrder } from "../../picker/types";
import styles from "../../scss/pages/PickerQueue.module.scss";

// Фильтр по типу задания: всё / только сборка / только объединение
type JobFilter = "all" | PickerJobType;

const JOB_TABS: { id: JobFilter; label: string }[] = [
  { id: "all", label: "Все" },
  { id: "source", label: "Сборка" },
  { id: "consolidation", label: "Объединение" },
];

/** Страница списка заказов сборщика.
 *  Одна страница на два таба нижнего меню, разница только в пропе `mode`:
 *    - mode="queue" → общая очередь (берём свободные заказы),
 *    - mode="mine"  → «Моя работа» (заказы, которые уже у нас в работе). */
export default function PickerQueuePage({ mode }: { mode: "queue" | "mine" }) {
  const navigate = useNavigate();
  const { queue, myOrders, take, loading, error, online, refresh } = usePicker();
  const [jobFilter, setJobFilter] = useState<JobFilter>("all");
  const visiblePage = usePageVisibility();

  // Layout уже загрузил списки при старте, поэтому первый polling откладываем.
  // После возврата online/из скрытой вкладки таймер создастся заново.
  useRecursivePolling({
    enabled: online && visiblePage,
    intervalMs: 30_000,
    refresh,
    runImmediately: false,
  });

  // Берём нужный список в зависимости от режима страницы.
  const orders = mode === "mine" ? myOrders : queue;

  // Отфильтрованный список по выбранной вкладке «Все / Сборка / Объединение».
  const visible = useMemo(() => {
    if (jobFilter === "all") return orders;
    return orders.filter((o) => o.job_type === jobFilter);
  }, [orders, jobFilter]);

  const title = mode === "mine" ? "Моя работа" : "Очередь сборки";

  return (
    <Box className={styles.page}>
      {/* Заголовок + кнопка «Обновить» (перезагружает очереди через контекст) */}
      <Box className={styles.header}>
        <Typography component="h1" className={styles.pageTitle}>
          {title}
        </Typography>
        <Button
          variant="outlined"
          size="small"
          onClick={() => refresh()}
          disabled={loading || !online}
        >
          Обновить
        </Button>
      </Box>

      <Typography className={styles.pageSubtitle}>
        {mode === "mine"
          ? "Только взятые вами активные задания; после завершения они уходят из списка"
          : "Свободные подтверждённые задания; обновление каждые 30 секунд"}
      </Typography>

      {/* Фильтры по типу работы */}
      <Tabs
        value={jobFilter}
        onChange={(_, v) => setJobFilter(v as JobFilter)}
        className={styles.tabs}
        variant="scrollable"
        scrollButtons={false}
      >
        {JOB_TABS.map((t) => (
          <Tab key={t.id} value={t.id} label={t.label} />
        ))}
      </Tabs>

      {error && <Typography className={styles.errorText}>{error}</Typography>}

      {/* Список карточек; пустое состояние — если заказов нет */}
      <Box className={styles.orderList}>
        {!loading && visible.length === 0 && (
          <Box className={styles.emptyWrap}>
            <Inventory2Icon className={styles.emptyIcon} />
            <Typography className={styles.emptyText}>
              {mode === "mine"
                ? "Активных заданий нет"
                : "В очереди пусто"}
            </Typography>
          </Box>
        )}
        {visible.map((order) => (
          <OrderCard
            key={order.id}
            order={order}
            onOpen={() => navigate(`/picker/orders/${order.id}`)}
            onTake={() => take(order.id)}
            online={online}
          />
        ))}
      </Box>
    </Box>
  );
}

/** Карточка одного заказа. Клик по карточке открывает детали,
 *  а кнопка «Взять» забирает заказ в работу (и НЕ открывает детали —
 *  для этого событию клика гасим всплытие через stopPropagation). */
function OrderCard({
  order,
  onOpen,
  onTake,
  online,
}: {
  order: PickerOrder;
  onOpen: () => void;
  onTake: () => void;
  online: boolean;
}) {
  const giftCount = order.gifts.length;
  const itemCount = order.items.length;
  const from = order.warehouse?.name ?? "—"; // откуда собираем
  const to = order.destination_warehouse?.name ?? "—"; // куда везём

  return (
    <Paper className={styles.orderCard} elevation={0} onClick={onOpen}>
      {/* Номер задания и клиентский заказ */}
      <Box className={styles.orderHeader}>
        <Typography className={styles.orderNumber}>
          {order.order_number}
        </Typography>
        <Typography className={styles.customerOrder}>
          заказ № {order.customer_order_number}
        </Typography>
      </Box>

      {/* Чипы: тип работы (иконка разная), статус, кто собирает */}
      <Box className={styles.chipsRow}>
        <Chip
          size="small"
          className={styles.jobChip}
          icon={order.job_type === "source" ? <Inventory2Icon /> : <HandymanIcon />}
          label={order.job_type === "source" ? "Сборка" : "Объединение"}
          variant="outlined"
        />
        <Chip
          size="small"
          label={order.status_name}
          color={statusColor(order.status)}
        />
        {order.picker && (
          <Chip size="small" label={`собирает: ${order.picker.name}`} variant="outlined" />
        )}
      </Box>

      {/* Маршрут: из какого склада в какой */}
      <Box className={styles.route}>
        <Typography className={styles.routeText}>
          {from} <ArrowForwardIcon fontSize="inherit" /> {to ?? "—"}
        </Typography>
      </Box>

      {/* Подвал: краткий состав + кнопка «Взять» (если сервер разрешает) */}
      <Box className={styles.orderFooter}>
        <Typography className={styles.metaText}>
          {giftCount > 0
            ? `${giftCount} подарочн. · ${itemCount} поз.`
            : `${itemCount} поз.`}
        </Typography>
        {order.actions.can_take && (
          <Button
            variant="contained"
            size="small"
            className={styles.takeBtn}
            onClick={(e) => {
              e.stopPropagation(); // не открывать детали при клике на кнопку
              onTake();
            }}
            disabled={!online}
          >
            Взять
          </Button>
        )}
      </Box>
    </Paper>
  );
}

// Цвет чипа статуса: в работе — жёлтый, готов к доставке — зелёный,
// в пути/доставлен — зелёный, всё остальное — серый.
function statusColor(
  status: string
): "default" | "primary" | "success" | "warning" {
  switch (status) {
    case "processing":
      return "warning";
    case "ready_for_delivery":
      return "primary";
    case "shipped":
    case "delivered":
      return "success";
    default:
      return "default";
  }
}
