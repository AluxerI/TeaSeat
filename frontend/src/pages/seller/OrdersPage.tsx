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
import AddIcon from "@mui/icons-material/Add";
import CloudUploadIcon from "@mui/icons-material/CloudUpload";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import SyncIcon from "@mui/icons-material/Sync";
import FlagIcon from "@mui/icons-material/Flag";
import { useSeller } from "../../contexts/SellerContext";
import { formatMoney, previewOrderTotal } from "../../seller/quantity";
import type { LocalOrderStatus } from "../../seller/types";
import styles from "../../scss/pages/SellerOrders.module.scss";

type Filter = "all" | LocalOrderStatus;

const FILTERS: { id: Filter; label: string }[] = [
  { id: "all", label: "Все" },
  { id: "draft", label: "Черновики" },
  { id: "queued", label: "В очереди" },
  { id: "synced", label: "Синхронизированные" },
  { id: "rejected", label: "Отклонённые" },
];

export default function OrdersPage() {
  const navigate = useNavigate();
  const {
    orders,
    pendingCount,
    syncOrder,
    syncAll,
    syncing,
    completeDay,
  } = useSeller();
  const [filter, setFilter] = useState<Filter>("all");
  const [syncingAll, setSyncingAll] = useState(false);

  const visible = useMemo(() => {
    if (filter === "all") return orders;
    return orders.filter((o) => o.status === filter);
  }, [orders, filter]);

  const drafts = orders.filter((o) => o.status === "draft").length;
  const queued = orders.filter((o) => o.status === "queued").length;

  const handleSyncAll = async () => {
    setSyncingAll(true);
    try {
      await syncAll();
    } finally {
      setSyncingAll(false);
    }
  };

  const handleCompleteDay = async () => {
    await completeDay();
  };

  return (
    <Box className={styles.page}>
      <Box className={styles.header}>
        <Typography component="h1" className={styles.pageTitle}>
          Заказы
        </Typography>
        <Button
          variant="contained"
          className={styles.newOrderBtn}
          startIcon={<AddIcon />}
          onClick={() => navigate("/seller/order/new")}
        >
          Новый заказ
        </Button>
      </Box>

      <Box className={styles.statRow}>
        <Stat label="Всего" value={orders.length} />
        <Stat label="Черновики" value={drafts} />
        <Stat label="В очереди" value={queued} />
        <Stat label="На сервере" value={orders.filter((o) => o.status === "synced").length} />
      </Box>

      {pendingCount > 0 && (
        <Paper className={styles.syncBanner} elevation={0}>
          <Typography className={styles.syncBannerText}>
            Не синхронизировано заказов: {pendingCount}
          </Typography>
          <Box className={styles.bannerActions}>
            <Button
              variant="contained"
              startIcon={<CloudUploadIcon />}
              onClick={handleSyncAll}
              disabled={syncingAll}
            >
              {syncingAll ? "Синхронизация..." : "Синхронизировать все"}
            </Button>
            <Button
              variant="outlined"
              startIcon={<FlagIcon />}
              onClick={handleCompleteDay}
              title="Провести все готовые заказы"
            >
              Завершить день
            </Button>
          </Box>
        </Paper>
      )}

      <Tabs
        value={filter}
        onChange={(_, v) => setFilter(v as Filter)}
        className={styles.tabs}
        variant="scrollable"
        scrollButtons={false}
      >
        {FILTERS.map((f) => (
          <Tab key={f.id} value={f.id} label={f.label} />
        ))}
      </Tabs>

      <Box className={styles.orderList}>
        {visible.length === 0 && (
          <Box className={styles.emptyWrap}>
            <CheckCircleIcon className={styles.emptyIcon} />
            <Typography className={styles.emptyText}>
              {filter === "all" ? "Заказов пока нет" : "В этой категории пусто"}
            </Typography>
            <Button
              variant="contained"
              className={styles.actionBtn}
              onClick={() => navigate("/seller/order/new")}
            >
              Создать новый заказ
            </Button>
          </Box>
        )}
        {visible.map((order) => {
          const total = previewOrderTotal(order.items);
          const isBusy = syncing.has(order.client_order_id);
          const needsSync = order.status === "queued" || order.status === "rejected";
          return (
            <Paper
              key={order.client_order_id}
              className={styles.orderCard}
              elevation={0}
              onClick={() => navigate(`/seller/orders/${order.client_order_id}`)}
            >
              <Box className={styles.orderHeader}>
                <Typography className={styles.orderId}>
                  {order.order_number ?? `#${order.client_order_id.slice(0, 8)}`}
                </Typography>
                <Typography className={styles.orderDate}>
                  {new Date(order.created_at).toLocaleString("ru-RU", {
                    day: "2-digit",
                    month: "2-digit",
                    hour: "2-digit",
                    minute: "2-digit",
                  })}
                </Typography>
              </Box>
              <Box className={styles.orderBody}>
                <Box>
                  <Typography className={styles.orderTotal}>
                    {formatMoney(total)} · {order.items.length} поз.
                  </Typography>
                  <Typography className={styles.orderPayment}>
                    {order.payment_method === "cash" ? "Наличные" : order.payment_method === "card" ? "Карта" : "Оплата не выбрана"}
                  </Typography>
                </Box>
                <Box className={styles.orderRight}>
                  <Chip
                    className={styles.statusChip}
                    label={statusLabel(order.status)}
                    color={statusColor(order.status)}
                    size="small"
                  />
                  {needsSync && (
                    <Button
                      variant="outlined"
                      className={styles.syncBtn}
                      startIcon={<SyncIcon />}
                      size="small"
                      disabled={isBusy}
                      onClick={(e) => {
                        e.stopPropagation();
                        syncOrder(order.client_order_id);
                      }}
                    >
                      {isBusy ? "..." : "Синхронизировать"}
                    </Button>
                  )}
                </Box>
              </Box>
            </Paper>
          );
        })}
      </Box>
    </Box>
  );
}

function Stat({ label, value }: { label: string; value: number }) {
  return (
    <Paper className={styles.statCard} elevation={0}>
      <Typography className={styles.statValue}>{value}</Typography>
      <Typography className={styles.statLabel}>{label}</Typography>
    </Paper>
  );
}

function statusLabel(status: LocalOrderStatus): string {
  switch (status) {
    case "draft":
      return "Черновик";
    case "queued":
      return "В очереди";
    case "syncing":
      return "Синхронизация";
    case "rejected":
      return "Отклонён";
    case "synced":
      return "Синхронизирован";
  }
}

function statusColor(
  status: LocalOrderStatus
): "default" | "success" | "error" | "warning" {
  switch (status) {
    case "synced":
      return "success";
    case "rejected":
      return "error";
    case "queued":
    case "syncing":
      return "warning";
    case "draft":
      return "default";
  }
}
