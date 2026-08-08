import { useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import {
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  Paper,
  Typography,
} from "@mui/material";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import EditIcon from "@mui/icons-material/Edit";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import SyncIcon from "@mui/icons-material/Sync";
import CheckCircleIcon from "@mui/icons-material/CheckCircle";
import CancelIcon from "@mui/icons-material/Cancel";
import SupportAgentIcon from "@mui/icons-material/SupportAgent";
import WarningAmberIcon from "@mui/icons-material/WarningAmber";
import { useSeller } from "../../contexts/SellerContext";
import { formatMoney, formatQuantity, previewLineTotal } from "../../seller/quantity";
import type { LocalOrder, OutboxAction } from "../../seller/types";
import styles from "../../scss/pages/SellerOrderDetail.module.scss";

type ConfirmAction = Exclude<OutboxAction, "upsert"> | "delete" | "edit";

export default function OrderDetailPage() {
  const navigate = useNavigate();
  const { clientOrderId } = useParams<{ clientOrderId: string }>();
  const {
    orders,
    syncOrder,
    enqueue,
    deleteOrder,
    reopen,
    syncing,
    openDraft,
    pendingCount,
  } = useSeller();

  const order = useMemo(
    () => orders.find((o) => o.client_order_id === clientOrderId),
    [orders, clientOrderId]
  );

  const [confirm, setConfirm] = useState<ConfirmAction | null>(null);

  if (!order) {
    return (
      <Box className={styles.centerWrap}>
        <Typography className={styles.emptyText}>Заказ не найден</Typography>
        <Button variant="contained" onClick={() => navigate("/seller/orders")}>
          К заказам
        </Button>
      </Box>
    );
  }

  const isBusy = syncing.has(order.client_order_id);
  const hasPending =
    order.status === "queued" ||
    order.status === "syncing" ||
    order.status === "rejected";

  const total = order.items.reduce((s, i) => s + previewLineTotal(i), 0);

  const runConfirm = async () => {
    if (!confirm) return;
    if (confirm === "delete") {
      await deleteOrder(order.client_order_id);
      navigate("/seller/orders");
    } else if (confirm === "edit") {
      await openDraft(order.client_order_id);
      navigate("/seller/order/new");
    } else {
      await enqueue(order.client_order_id, confirm);
    }
    setConfirm(null);
  };

  const confirmMeta = confirm
    ? CONFIRM_META[confirm]
    : null;

  return (
    <Box className={styles.page}>
      <Box className={styles.header}>
        <Button className={styles.backBtn} startIcon={<ArrowBackIcon />} onClick={() => navigate("/seller/orders")}>
          Заказы
        </Button>
        <Typography component="h1" className={styles.pageTitle}>
          {order.order_number ?? `Заказ #${order.client_order_id.slice(0, 8)}`}
        </Typography>
        <Chip
          className={styles.statusChip}
          label={statusLabel(order)}
          color={statusColor(order)}
        />
      </Box>

      <Box className={styles.summary}>
        <Paper className={styles.summaryCard} elevation={0}>
          <Typography className={styles.summaryLabel}>Сумма</Typography>
          <Typography className={styles.summaryValue}>{formatMoney(total)}</Typography>
          {order.server_id !== null && (
            <Typography className={styles.summaryHint}>№ {order.order_number}</Typography>
          )}
        </Paper>
        <Paper className={styles.summaryCard} elevation={0}>
          <Typography className={styles.summaryLabel}>Оплата</Typography>
          <Typography className={styles.summaryValue}>
            {order.payment_method === "cash" ? "Наличные" : order.payment_method === "card" ? "Карта" : "—"}
          </Typography>
        </Paper>
        <Paper className={styles.summaryCard} elevation={0}>
          <Typography className={styles.summaryLabel}>Время продажи</Typography>
          <Typography className={styles.summaryValue}>
            {new Date(order.occurred_at).toLocaleTimeString("ru-RU", { hour: "2-digit", minute: "2-digit" })}
          </Typography>
        </Paper>
      </Box>

      {hasPending && (
        <Paper className={styles.syncBanner} elevation={0}>
          <Box className={styles.syncBannerText}>
            <SyncIcon />
            <Typography className={styles.syncBannerLabel}>
              {order.status === "rejected"
                ? "Заказ отклонён сервером"
                : "Заказ ожидает синхронизации"}
            </Typography>
          </Box>
          <Button
            variant="contained"
            startIcon={<SyncIcon />}
            onClick={() => syncOrder(order.client_order_id)}
            disabled={isBusy}
          >
            {isBusy ? "..." : "Синхронизировать"}
          </Button>
        </Paper>
      )}

      {order.last_error && (
        <Paper className={styles.errorCard} elevation={0}>
          <WarningAmberIcon className={styles.errorIcon} />
          <Typography className={styles.errorText}>{order.last_error}</Typography>
        </Paper>
      )}

      {order.conflicts.length > 0 && (
        <Paper className={styles.errorCard} elevation={0}>
          <WarningAmberIcon className={styles.errorIcon} />
          <Box>
            <Typography className={styles.errorText}>
              Конфликты при проведении продажи:
            </Typography>
            {order.conflicts.map((c, i) => (
              <Typography key={i} className={styles.conflictLine}>
                {c.product_id} — не хватает {c.shortage_quantity}
              </Typography>
            ))}
          </Box>
        </Paper>
      )}

      <Paper className={styles.itemsCard} elevation={0}>
        <Typography className={styles.sectionTitle}>Состав</Typography>
        {order.items.map((item) => (
          <Box key={item.product_id} className={styles.itemLine}>
            <Box>
              <Typography className={styles.itemName}>{item.name}</Typography>
              <Typography className={styles.itemMeta}>
                {formatMoney(item.unit_price)} × {formatQuantity(item.quantity, item.stock_unit)}
              </Typography>
            </Box>
            <Typography className={styles.itemTotal}>
              {formatMoney(previewLineTotal(item))}
            </Typography>
          </Box>
        ))}
      </Paper>

      {order.customer_note && (
        <Paper className={styles.itemsCard} elevation={0}>
          <Typography className={styles.sectionTitle}>Комментарий клиента</Typography>
          <Typography className={styles.noteText}>{order.customer_note}</Typography>
        </Paper>
      )}

      <Box className={styles.actions}>
        {(order.status === "draft" || order.actions?.can_edit) && (
          <Button
            variant="contained"
            startIcon={<EditIcon />}
            onClick={() => setConfirm("edit")}
          >
            {order.status === "draft" ? "Продолжить оформление" : "Изменить заказ"}
          </Button>
        )}
        {order.actions?.can_cancel && (
          <Button
            variant="outlined"
            color="error"
            startIcon={<CancelIcon />}
            onClick={() => setConfirm("cancel")}
          >
            Отменить заказ
          </Button>
        )}
        {order.actions?.can_complete && (
          <Button
            variant="contained"
            startIcon={<CheckCircleIcon />}
            onClick={() => setConfirm("complete")}
          >
            Провести продажу
          </Button>
        )}
        {order.actions?.can_escalate && (
          <Button
            variant="contained"
            color="warning"
            startIcon={<SupportAgentIcon />}
            onClick={() => setConfirm("escalate")}
          >
            Передать менеджеру
          </Button>
        )}
        {order.status === "draft" && (
          <Button
            variant="text"
            color="error"
            startIcon={<DeleteOutlineIcon />}
            onClick={() => setConfirm("delete")}
          >
            Удалить черновик
          </Button>
        )}
      </Box>

      <Dialog open={confirm !== null} onClose={() => setConfirm(null)}>
        <DialogTitle>{confirmMeta?.title}</DialogTitle>
        <DialogContent>
          <DialogContentText>{confirmMeta?.text}</DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setConfirm(null)}>Отмена</Button>
          <Button
            variant="contained"
            color={confirmMeta?.danger ? "error" : "primary"}
            onClick={runConfirm}
          >
            {confirmMeta?.action}
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}

const CONFIRM_META: Record<ConfirmAction, { title: string; text: string; action: string; danger?: boolean }> = {
  cancel: {
    title: "Отменить заказ?",
    text: "Заказ будет отменён на сервере, резерв продавца освободится.",
    action: "Отменить",
    danger: true,
  },
  complete: {
    title: "Провести продажу?",
    text: "Товар будет списан с остатка. При недостаче заказ уйдёт на проверку менеджеру.",
    action: "Провести",
  },
  escalate: {
    title: "Передать менеджеру?",
    text: "Менеджер разберёт конфликт по этому заказу.",
    action: "Передать",
  },
  delete: {
    title: "Удалить черновик?",
    text: "Черновик будет удалён безвозвратно.",
    action: "Удалить",
    danger: true,
  },
  edit: {
    title: "Изменить заказ?",
    text: "Заказ снова откроется в оформлении. Изменённая версия синхронизируется с сервером.",
    action: "Изменить",
  },
};

function statusLabel(order: LocalOrder): string {
  switch (order.status) {
    case "draft":
      return "Черновик";
    case "queued":
      return "Ожидает синхронизации";
    case "syncing":
      return "Синхронизация...";
    case "rejected":
      return "Отклонён";
    case "synced":
      return order.server_status_name ?? "Синхронизирован";
    default:
      return order.status;
  }
}

function statusColor(
  order: LocalOrder
): "default" | "primary" | "success" | "error" | "warning" {
  switch (order.status) {
    case "draft":
      return "default";
    case "queued":
    case "syncing":
      return "warning";
    case "rejected":
      return "error";
    case "synced":
      return order.server_status === "completed" ? "success" : "primary";
    default:
      return "default";
  }
}
