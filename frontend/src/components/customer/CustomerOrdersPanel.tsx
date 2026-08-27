import { useCallback, useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Alert, Box, Button, Chip, CircularProgress, Paper, Typography } from "@mui/material";
import ArrowForwardRoundedIcon from "@mui/icons-material/ArrowForwardRounded";
import Inventory2OutlinedIcon from "@mui/icons-material/Inventory2Outlined";
import LocalShippingOutlinedIcon from "@mui/icons-material/LocalShippingOutlined";
import { orderApi } from "../../api/orderAPI";
import type { Order } from "../../interfaces/order";
import { extractError, translateError } from "../../utils/translateError";
import { formatMoney } from "../../seller/quantity";
import styles from "../../scss/pages/CustomerOrders.module.scss";

// Переводим бизнес-статус backend в ограниченный набор семантических цветов MUI.
const STATUS_COLORS: Record<string, "default" | "warning" | "info" | "success" | "error"> = {
  pending: "warning", confirmed: "info", processing: "info", ready_for_delivery: "info",
  shipped: "info", awaiting_receipt: "warning", delivered: "success", completed: "success",
  cancelled: "error", seller_review: "warning", manager_review: "error",
};

export default function CustomerOrdersPanel() {
  const navigate = useNavigate();
  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  // customer_id не передаём: backend определяет владельца по текущей сессии.
  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try { setOrders(await orderApi.getOrders()); }
    catch (err) { setError(translateError(extractError(err))); }
    finally { setLoading(false); }
  }, []);

  useEffect(() => { void load(); }, [load]);

  if (loading) return <Box className={styles.state}><CircularProgress size={32} /></Box>;
  if (error) return <Box className={styles.state}><Alert severity="error">{error}</Alert><Button onClick={load}>Повторить</Button></Box>;
  if (orders.length === 0) return <Paper className={styles.empty} elevation={0}><Inventory2OutlinedIcon /><Typography>У вас пока нет оформленных заказов.</Typography><Button variant="contained" onClick={() => navigate("/catalog")}>Перейти в каталог</Button></Paper>;

  return <Box className={styles.list}>{orders.map((order) => {
    // Компоненты подарков есть и в items; исключаем их из счётчика обычных товаров.
    const productLines = order.items.filter((item) => !order.gifts.some((gift) => gift.items.some((giftItem) => giftItem.id === item.id))).length;
    return <Paper key={order.id} className={styles.card} elevation={0}>
      <Box className={styles.cardTop}><Box><Typography className={styles.number}>Заказ №{order.order_number}</Typography><Typography className={styles.date}>{order.timestamps.created_at}</Typography></Box><Chip size="small" color={STATUS_COLORS[order.status] ?? "default"} label={order.status_info.name} /></Box>
      <Box className={styles.meta}><Box><Inventory2OutlinedIcon /><span>{productLines} товаров · {order.gifts.length} подарков</span></Box><Box><LocalShippingOutlinedIcon /><span>{order.delivery.method?.name ?? "Способ доставки уточняется"}</span></Box></Box>
      <Box className={styles.cardBottom}><Typography className={styles.total}>{formatMoney(order.totals.final_total)}</Typography><Button endIcon={<ArrowForwardRoundedIcon />} onClick={() => navigate(`/order/${order.id}`)}>Подробнее</Button></Box>
    </Paper>;
  })}</Box>;
}
