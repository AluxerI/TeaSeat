import { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import {
  Box,
  Typography,
  Button,
  Paper,
  Divider,
  Chip,
  CircularProgress,
} from "@mui/material";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import LocalShippingOutlinedIcon from "@mui/icons-material/LocalShippingOutlined";
import CalendarTodayOutlinedIcon from "@mui/icons-material/CalendarTodayOutlined";
import CancelOutlinedIcon from "@mui/icons-material/CancelOutlined";
import RedeemOutlinedIcon from "@mui/icons-material/RedeemOutlined";

import Header from "../ui/header/header";
import Footer from "../ui/footer/Footer";
import { useAuth } from "../hooks/useAuth";
import { orderApi } from "../api/orderAPI";
import { translateError, extractError } from "../utils/translateError";
import { normalizeAssetUrl } from "../utils/assetUrl";
import type { Order, TabKey, TabItem } from "../interfaces/order";
import styles from "../scss/pages/Order.module.scss";

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat("ru-RU", {
    style: "currency",
    currency: "RUB",
    minimumFractionDigits: 2,
  }).format(amount);
}

const STATUS_COLORS: Record<string, string> = {
  gray: "#8a7d6f",
  yellow: "#d4a017",
  blue: "#4a7fb5",
  indigo: "#5c4db8",
  purple: "#8b5cf6",
  green: "#5a8a2a",
  red: "#c9564e",
};

const TABS: TabItem[] = [
  { key: "items", label: "Состав заказа", icon: <></> },
  { key: "details", label: "Детали", icon: <></> },
];

export default function OrderPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user, loading: authLoading } = useAuth();

  const [order, setOrder] = useState<Order | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [cancelling, setCancelling] = useState(false);
  const [activeTab, setActiveTab] = useState<TabKey>("items");

  useEffect(() => {
    // Backend ограничивает заказ текущим покупателем; id определяет только ресурс.
    if (!id || authLoading) return;
    if (!user) {
      navigate("/login");
      return;
    }

    setLoading(true);
    orderApi
      .getOrder(Number(id))
      .then(setOrder)
      .catch((err) => setError(translateError(extractError(err))))
      .finally(() => setLoading(false));
  }, [id, user, authLoading, navigate]);

  const handleCancel = async () => {
    // Ответ отмены уже содержит свежий Order, отдельный GET не требуется.
    if (!order) return;
    setCancelling(true);
    try {
      setOrder(await orderApi.cancelOrder(order.id));
    } catch (err: any) {
      setError(translateError(extractError(err)));
    } finally {
      setCancelling(false);
    }
  };

  const statusColor = STATUS_COLORS[order?.status_info?.color ?? ""] || "#8a7d6f";

  if (loading || authLoading) {
    return (
      <Box className={styles.page}>
        <Header />
        <Box className={styles.loadingWrap}>
          <CircularProgress />
        </Box>
        <Footer />
      </Box>
    );
  }

  if (error || !order) {
    return (
      <Box className={styles.page}>
        <Header />
        <Box className={styles.loadingWrap}>
          <Typography className={styles.errorText}>
            {error || "Заказ не найден"}
          </Typography>
          <Button
            variant="outlined"
            className={styles.secondaryButton}
            onClick={() => navigate("/profile?section=orders")}
          >
            Вернуться в профиль
          </Button>
        </Box>
        <Footer />
      </Box>
    );
  }

  // UI скрывает невозможное действие, backend всё равно проверяет переход статуса.
  const canCancel = ["pending", "confirmed"].includes(order.status);
  // Компоненты подарков есть в items и gifts; множество исключает двойную отрисовку.
  const giftItemIds = new Set(order.gifts.flatMap((gift) => gift.items.map((item) => item.id)));

  return (
    <Box className={styles.page}>
      <Header />

      <Box className={styles.orderPage}>
        <Box className={styles.backRow}>
          <Button
            className={styles.backButton}
            startIcon={<ArrowBackIcon />}
            onClick={() => navigate("/profile?section=orders")}
            disableRipple
          >
            Назад в заказы
          </Button>
        </Box>

        <Box className={styles.orderHeader}>
          <Box>
            <Typography component="h1" className={styles.orderNumber}>
              Заказ №{order.order_number}
            </Typography>
            <Box className={styles.headerMeta}>
              <Chip
                label={order.status_info.name}
                className={styles.statusChip}
                sx={{
                  backgroundColor: statusColor,
                  color: "#fff",
                  fontWeight: 700,
                  fontSize: "12px",
                }}
              />
              <Typography className={styles.orderType}>
                {order.order_type_name}
              </Typography>
            </Box>
          </Box>

          <Box className={styles.headerActions}>
            {canCancel && (
              <Button
                variant="outlined"
                className={styles.cancelButton}
                startIcon={<CancelOutlinedIcon />}
                onClick={handleCancel}
                disabled={cancelling}
              >
                {cancelling ? "Отмена…" : "Отменить заказ"}
              </Button>
            )}
          </Box>
        </Box>

        <Box className={styles.tabRow}>
          {TABS.map((tab) => (
            <button
              key={tab.key}
              className={`${styles.tab} ${activeTab === tab.key ? styles.tabActive : ""}`}
              onClick={() => setActiveTab(tab.key)}
            >
              {tab.label}
            </button>
          ))}
        </Box>

        <Box className={styles.orderLayout}>
          <Box component="main" className={styles.orderMain}>
            {activeTab === "items" && (
              <Box className={styles.itemsList}>
                {order.items.filter((item) => !giftItemIds.has(item.id)).map((item) => (
                  <Paper key={item.id} className={styles.itemCard} elevation={0}>
                    <Box
                      className={styles.itemImage}
                      sx={{
                        backgroundImage: item.product?.image
                          ? `url(${normalizeAssetUrl(item.product.image)})`
                          : "none",
                        backgroundColor: item.product?.image ? "transparent" : "#f5ead9",
                      }}
                    />
                    <Box className={styles.itemInfo}>
                      <Typography className={styles.itemName}>
                        {item.product?.name || "Товар"}
                      </Typography>
                      {item.product?.weight_grams && (
                        <Typography className={styles.itemWeight}>
                          {item.product.weight_grams} г
                        </Typography>
                      )}
                      <Typography className={styles.itemQty}>
                        {item.quantity} шт. ×{" "}
                        {formatCurrency(item.prices.final_unit_price)}
                      </Typography>
                    </Box>
                    <Box className={styles.itemTotal}>
                      <Typography className={styles.itemPrice}>
                        {formatCurrency(item.prices.total_price)}
                      </Typography>
                      {item.prices.promotion_discount_percent > 0 && (
                        <Typography className={styles.itemDiscount}>
                          -{item.prices.promotion_discount_percent}%
                        </Typography>
                      )}
                    </Box>
                  </Paper>
                ))}
                {order.gifts.map((gift) => (
                  <Paper key={`gift:${gift.id}`} className={`${styles.itemCard} ${styles.giftCard}`} elevation={0}>
                    <Box className={`${styles.itemImage} ${styles.giftImage}`}><RedeemOutlinedIcon /></Box>
                    <Box className={styles.itemInfo}>
                      <Typography className={styles.itemName}>{gift.name}</Typography>
                      <Typography className={styles.itemQty}>{gift.quantity} шт. · {gift.items.length} компонентов</Typography>
                      <Typography className={styles.itemWeight}>{gift.items.map((item) => item.product?.name ?? "Товар").join(" · ")}</Typography>
                    </Box>
                    <Box className={styles.itemTotal}><Typography className={styles.itemPrice}>{formatCurrency(gift.prices.total_price)}</Typography></Box>
                  </Paper>
                ))}
              </Box>
            )}

            {activeTab === "details" && (
              <Box className={styles.detailsSection}>
                {order.customer_notes && (
                  <Paper className={styles.detailCard} elevation={0}>
                    <Typography className={styles.detailLabel}>
                      Комментарий к заказу
                    </Typography>
                    <Typography className={styles.detailValue}>
                      {order.customer_notes}
                    </Typography>
                  </Paper>
                )}

                <Paper className={styles.detailCard} elevation={0}>
                  <Typography className={styles.detailLabel}>
                    Способ оплаты
                  </Typography>
                  <Typography className={styles.detailValue}>
                    {order.payment_method === "cash"
                      ? "Наличными"
                      : order.payment_method === "card"
                        ? "Картой"
                        : "Онлайн"}
                  </Typography>
                </Paper>

                {order.supplier_info && (
                  <Paper className={styles.detailCard} elevation={0}>
                    <Typography className={styles.detailLabel}>
                      Поставщик
                    </Typography>
                    <Typography className={styles.detailValue}>
                      {order.supplier_info.message}
                    </Typography>
                  </Paper>
                )}
              </Box>
            )}
          </Box>

          <Box component="aside" className={styles.orderSidebar}>
            <Paper className={styles.sidebarCard} elevation={0}>
              <Typography className={styles.sidebarTitle}>
                Сумма заказа
              </Typography>
              <Box className={styles.totalRow}>
                <Typography className={styles.totalLabel}>Товары</Typography>
                <Typography className={styles.totalValue}>
                  {formatCurrency(order.totals.products_total)}
                </Typography>
              </Box>
              {order.totals.promotion_discount > 0 && (
                <Box className={styles.totalRow}>
                  <Typography className={styles.totalLabel}>
                    Скидка по акции
                  </Typography>
                  <Typography className={styles.totalDiscount}>
                    -{formatCurrency(order.totals.promotion_discount)}
                  </Typography>
                </Box>
              )}
              {order.totals.personal_discount > 0 && (
                <Box className={styles.totalRow}>
                  <Typography className={styles.totalLabel}>
                    Персональная скидка
                  </Typography>
                  <Typography className={styles.totalDiscount}>
                    -{formatCurrency(order.totals.personal_discount)}
                  </Typography>
                </Box>
              )}
              {order.totals.cart_discount > 0 && (
                <Box className={styles.totalRow}>
                  <Typography className={styles.totalLabel}>
                    Скидка корзины
                  </Typography>
                  <Typography className={styles.totalDiscount}>
                    -{formatCurrency(order.totals.cart_discount)}
                  </Typography>
                </Box>
              )}
              <Box className={styles.totalRow}>
                <Typography className={styles.totalLabel}>
                  Доставка
                </Typography>
                <Typography className={styles.totalValue}>
                  {order.totals.shipping_cost > 0
                    ? formatCurrency(order.totals.shipping_cost)
                    : "Бесплатно"}
                </Typography>
              </Box>
              <Divider className={styles.totalDivider} />
              <Box className={styles.totalRow}>
                <Typography className={styles.totalLabelFinal}>
                  Итого
                </Typography>
                <Typography className={styles.totalValueFinal}>
                  {formatCurrency(order.totals.final_total)}
                </Typography>
              </Box>
            </Paper>

            <Paper className={styles.sidebarCard} elevation={0}>
              <Typography className={styles.sidebarTitle}>
                <LocalShippingOutlinedIcon
                  fontSize="small"
                  className={styles.sidebarIcon}
                />
                Доставка
              </Typography>
              <Typography className={styles.deliveryMethod}>
                {order.delivery.method?.name ?? "Способ доставки уточняется"}
              </Typography>
              {order.delivery.address && (
                <Typography className={styles.deliveryAddress}>
                  {order.delivery.address.full_address}
                </Typography>
              )}
              {order.delivery.scheduled_window && (
                <Typography className={styles.deliveryAddress}>
                  {order.delivery.scheduled_window.date}, {order.delivery.scheduled_window.time_from}–{order.delivery.scheduled_window.time_to}
                </Typography>
              )}
              {order.delivery.tracking_number && (
                <Box className={styles.trackingRow}>
                  <Typography className={styles.trackingLabel}>
                    Трек-номер
                  </Typography>
                  <Typography className={styles.trackingValue}>
                    {order.delivery.tracking_number}
                  </Typography>
                </Box>
              )}
            </Paper>

            <Paper className={styles.sidebarCard} elevation={0}>
              <Typography className={styles.sidebarTitle}>
                <CalendarTodayOutlinedIcon
                  fontSize="small"
                  className={styles.sidebarIcon}
                />
                Время заказа
              </Typography>
              {renderTimeline(order)}
            </Paper>
          </Box>
        </Box>
      </Box>

      <Footer />
    </Box>
  );
}

function renderTimeline(order: Order) {
  // Незаполненная дата — ещё не пройденный этап; прогресс берём из событий backend.
  const events: { label: string; date: string | null }[] = [
    { label: "Создан", date: order.timestamps.created_at },
    { label: "Подтверждён", date: order.timestamps.confirmed_at },
    { label: "Оплачен", date: order.timestamps.paid_at },
    { label: "Отправлен", date: order.timestamps.shipped_at },
    { label: "Доставлен", date: order.timestamps.delivered_at },
  ];

  const isCancelled = order.status === "cancelled";

  return (
    <Box className={styles.timeline}>
      {events.map((event, i) => {
        const isPast = !!event.date;
        return (
          <Box
            key={event.label}
            className={`${styles.timelineItem} ${isPast ? styles.timelinePast : ""}`}
          >
            <Box className={styles.timelineDot} />
            <Box>
              <Typography className={styles.timelineLabel}>
                {event.label}
              </Typography>
              {event.date && (
                <Typography className={styles.timelineDate}>
                  {event.date}
                </Typography>
              )}
            </Box>
          </Box>
        );
      })}
      {isCancelled && (
        <Box className={`${styles.timelineItem} ${styles.timelinePast}`}>
          <Box className={styles.timelineDot} sx={{ backgroundColor: "#c9564e" }} />
          <Box>
            <Typography className={styles.timelineLabel}>Отменён</Typography>
            <Typography className={styles.timelineDate}>
              {order.timestamps.cancelled_at}
            </Typography>
          </Box>
        </Box>
      )}
    </Box>
  );
}
