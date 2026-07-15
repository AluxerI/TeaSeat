import { useState } from "react";
import { useNavigate } from "react-router-dom";
import {
  Box, Typography, Button, Paper,
} from "@mui/material";
import CloudUploadIcon from "@mui/icons-material/CloudUpload";
import SyncIcon from "@mui/icons-material/Sync";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import { useSeller } from "../../contexts/SellerContext";
import styles from "../../scss/pages/SellerOrders.module.scss";

export default function SellerOrdersPage() {
  const navigate = useNavigate();
  const { unsyncedOrders, syncOrder, syncAll } = useSeller();
  const [syncing, setSyncing] = useState<Set<string>>(new Set());
  const [syncingAll, setSyncingAll] = useState(false);

  const handleSyncOne = async (id: string) => {
    setSyncing((prev) => new Set(prev).add(id));
    await syncOrder(id);
    setSyncing((prev) => {
      const next = new Set(prev);
      next.delete(id);
      return next;
    });
  };

  const handleSyncAll = async () => {
    setSyncingAll(true);
    await syncAll();
    setSyncingAll(false);
  };

  if (unsyncedOrders.length === 0) {
    return (
      <Box className={styles.page}>
        <Typography component="h1" className={styles.pageTitle}>
          Заказы
        </Typography>
        <Box className={styles.emptyWrap}>
          <CheckCircleIcon className={styles.emptyIcon} />
          <Typography className={styles.emptyText}>
            Все заказы синхронизированы
          </Typography>
          <Button variant="contained" className={styles.actionBtn} onClick={() => navigate("/seller/catalog")}>
            Создать новый заказ
          </Button>
        </Box>
      </Box>
    );
  }

  return (
    <Box>
      <Box className={styles.header}>
        <Typography component="h1" className={styles.pageTitle}>
          Заказы ({unsyncedOrders.length})
        </Typography>
        <Button
          variant="contained"
          className={styles.syncAllBtn}
          startIcon={<CloudUploadIcon />}
          onClick={handleSyncAll}
          disabled={syncingAll}
        >
          {syncingAll ? "Синхронизация..." : "Синхронизировать все"}
        </Button>
      </Box>

      <Box className={styles.orderList}>
        {unsyncedOrders.map((order) => (
          <Paper key={order.id} className={styles.orderCard} elevation={0}>
            <Box className={styles.orderHeader}>
              <Typography className={styles.orderId}>
                #{order.id.slice(0, 8)}
              </Typography>
              <Typography className={styles.orderDate}>
                {new Date(order.created_at).toLocaleString("ru-RU")}
              </Typography>
            </Box>
            <Typography className={styles.orderCustomer}>
              {order.customer_name || "Без имени"} {order.customer_phone ? `— ${order.customer_phone}` : ""}
            </Typography>
            <Typography className={styles.orderTotal}>
              {order.total} ₽ · {order.items.length} поз.
            </Typography>
            <Button
              variant="outlined"
              className={styles.syncBtn}
              startIcon={<SyncIcon />}
              onClick={() => handleSyncOne(order.id)}
              disabled={syncing.has(order.id)}
              size="small"
            >
              {syncing.has(order.id) ? "..." : "Синхронизировать"}
            </Button>
          </Paper>
        ))}
      </Box>
    </Box>
  );
}
