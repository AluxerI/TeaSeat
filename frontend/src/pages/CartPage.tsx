import { useEffect, useState, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import {
  Box,
  Typography,
  Button,
  Paper,
  Divider,
  CircularProgress,
  IconButton,
} from "@mui/material";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import ShoppingCartOutlinedIcon from "@mui/icons-material/ShoppingCartOutlined";

import Header from "../ui/header/header";
import Footer from "../ui/footer/Footer";
import { useAuth } from "../hooks/useAuth";
import { cartApi } from "../api/cartAPI";
import { translateError, extractError } from "../utils/translateError";
import type { Cart } from "../interfaces/cart";
import styles from "../scss/pages/CartPage.module.scss";

/** Форматирование рублей (Intl.NumberFormat) */
function formatCurrency(amount: number): string {
  return new Intl.NumberFormat("ru-RU", {
    style: "currency",
    currency: "RUB",
    minimumFractionDigits: 2,
  }).format(amount);
}

export default function CartPage() {
  const navigate = useNavigate();
  const { user, loading: authLoading } = useAuth();

  const [cart, setCart] = useState<Cart | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  /**
   * Множество ID товаров, которые сейчас обновляются.
   * Используется для disabled-состояния кнопок и полупрозрачности карточки.
   */
  const [updatingItems, setUpdatingItems] = useState<Set<number>>(new Set());

  /** Загрузка корзины с сервера */
  const fetchCart = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const data = await cartApi.getCart();
      setCart(data);
    } catch (err: any) {
      setError(translateError(extractError(err)));
    } finally {
      setLoading(false);
    }
  }, []);

  /** При монтировании проверяем авторизацию и запрашиваем корзину */
  useEffect(() => {
    if (authLoading) return;
    if (!user) {
      navigate("/login");
      return;
    }
    fetchCart();
  }, [user, authLoading, navigate, fetchCart]);

  /** Обновить количество товара, уйти можно до 0 (удаляет через API) */
  const handleUpdateQty = async (itemId: number, newQty: number) => {
    setUpdatingItems((prev) => new Set(prev).add(itemId));
    try {
      const updatedCart = await cartApi.updateItemQuantity(itemId, newQty);
      setCart(updatedCart);
    } catch (err: any) {
      setError(translateError(extractError(err)));
    } finally {
      setUpdatingItems((prev) => {
        const next = new Set(prev);
        next.delete(itemId);
        return next;
      });
    }
  };

  /** Удалить товар из корзины */
  const handleRemove = async (itemId: number) => {
    setUpdatingItems((prev) => new Set(prev).add(itemId));
    try {
      const updatedCart = await cartApi.removeItem(itemId);
      setCart(updatedCart);
    } catch (err: any) {
      setError(translateError(extractError(err)));
    } finally {
      setUpdatingItems((prev) => {
        const next = new Set(prev);
        next.delete(itemId);
        return next;
      });
    }
  };

  // ── Loading ─────────────────────────────────────────────────────────
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

  const isEmpty = !error && cart && cart.items.length === 0;

  // ── Error (корзина не загрузилась) ──────────────────────────────────
  if (error && !cart) {
    return (
      <Box className={styles.page}>
        <Header />
        <Box className={styles.loadingWrap}>
          <Typography className={styles.errorText}>{error}</Typography>
          <Button
            variant="outlined"
            className={styles.secondaryButton}
            onClick={() => navigate("/catalog")}
          >
            Перейти в каталог
          </Button>
        </Box>
        <Footer />
      </Box>
    );
  }

  // ── Empty ───────────────────────────────────────────────────────────
  if (isEmpty || !cart) {
    return (
      <Box className={styles.page}>
        <Header />
        <Box className={styles.emptyWrap}>
          <ShoppingCartOutlinedIcon className={styles.emptyIcon} />
          <Typography className={styles.errorText}>
            Ваша корзина пуста
          </Typography>
          <Button
            variant="outlined"
            className={styles.secondaryButton}
            onClick={() => navigate("/catalog")}
          >
            Перейти в каталог
          </Button>
        </Box>
        <Footer />
      </Box>
    );
  }

  // ── Cart content ────────────────────────────────────────────────────
  return (
    <Box className={styles.page}>
      <Header />

      <Box className={styles.cartPage}>
        {/* Back link */}
        <Box className={styles.backRow}>
          <Button
            className={styles.backButton}
            startIcon={<ArrowBackIcon />}
            onClick={() => navigate("/catalog")}
            disableRipple
          >
            Продолжить покупки
          </Button>
        </Box>

        <Typography component="h1" className={styles.pageTitle}>
          Корзина
        </Typography>

        {/* Inline error (например, не удалось обновить количество) */}
        {error && (
          <Typography sx={{ color: "#c9564e", mb: 2, fontSize: 14 }}>
            {error}
          </Typography>
        )}

        <Box className={styles.cartLayout}>
          {/* ── Items list ──────────────────────────────────────────── */}
          <Box component="main" className={styles.cartMain}>
            <Box className={styles.itemsList}>
              {cart.items.map((item) => {
                const isUpdating = updatingItems.has(item.id);
                return (
                  <Paper
                    key={item.id}
                    className={`${styles.itemCard} ${isUpdating ? styles.itemUpdating : ""}`}
                    elevation={0}
                  >
                    {/* Image */}
                    <Box
                      className={styles.itemImage}
                      sx={{
                        backgroundImage: item.product?.image
                          ? `url(${item.product.image})`
                          : "none",
                        backgroundColor: item.product?.image
                          ? "transparent"
                          : "#f5ead9",
                      }}
                    />
                    {/* Name + weight + unit price */}
                    <Box className={styles.itemInfo}>
                      <Typography className={styles.itemName}>
                        {item.product?.name || "Товар"}
                      </Typography>
                      {item.product?.weight_grams && (
                        <Typography className={styles.itemWeight}>
                          {item.product.weight_grams} г
                        </Typography>
                      )}
                      <Typography className={styles.itemUnitPrice}>
                        {formatCurrency(item.final_unit_price)} / шт.
                      </Typography>
                    </Box>
                    {/* Quantity controls + total + remove */}
                    <Box className={styles.itemRight}>
                      <Box className={styles.qtyControls}>
                        <Button
                          className={styles.qtyBtn}
                          onClick={() => handleUpdateQty(item.id, item.quantity - 1)}
                          disabled={item.quantity <= 1 || isUpdating}
                        >
                          −
                        </Button>
                        <Typography className={styles.qtyValue}>
                          {item.quantity}
                        </Typography>
                        <Button
                          className={styles.qtyBtn}
                          onClick={() => handleUpdateQty(item.id, item.quantity + 1)}
                          disabled={item.quantity >= 100 || isUpdating}
                        >
                          +
                        </Button>
                      </Box>
                      <Typography className={styles.itemTotalPrice}>
                        {formatCurrency(item.total_price)}
                      </Typography>
                      {item.saved_amount > 0 && (
                        <Typography className={styles.itemSaved}>
                          Экономия {formatCurrency(item.saved_amount)}
                        </Typography>
                      )}
                      <IconButton
                        className={styles.removeBtn}
                        onClick={() => handleRemove(item.id)}
                        disabled={isUpdating}
                        size="small"
                      >
                        <DeleteOutlineIcon fontSize="small" />
                      </IconButton>
                    </Box>
                  </Paper>
                );
              })}
            </Box>
          </Box>

          {/* ── Summary sidebar ─────────────────────────────────────── */}
          <Box component="aside" className={styles.cartSidebar}>
            <Paper className={styles.sidebarCard} elevation={0}>
              <Typography className={styles.sidebarTitle}>
                Сумма заказа
              </Typography>
              <Box className={styles.totalRow}>
                <Typography className={styles.totalLabel}>Товары</Typography>
                <Typography className={styles.totalValue}>
                  {formatCurrency(cart.products_total)}
                </Typography>
              </Box>
              {cart.promotion_discount > 0 && (
                <Box className={styles.totalRow}>
                  <Typography className={styles.totalLabel}>
                    Скидка по акции
                  </Typography>
                  <Typography className={styles.totalDiscount}>
                    -{formatCurrency(cart.promotion_discount)}
                  </Typography>
                </Box>
              )}
              {cart.personal_discount > 0 && (
                <Box className={styles.totalRow}>
                  <Typography className={styles.totalLabel}>
                    Персональная скидка
                  </Typography>
                  <Typography className={styles.totalDiscount}>
                    -{formatCurrency(cart.personal_discount)}
                  </Typography>
                </Box>
              )}
              {cart.cart_discount > 0 && (
                <Box className={styles.totalRow}>
                  <Typography className={styles.totalLabel}>
                    Скидка корзины
                  </Typography>
                  <Typography className={styles.totalDiscount}>
                    -{formatCurrency(cart.cart_discount)}
                  </Typography>
                </Box>
              )}
              <Box className={styles.totalRow}>
                <Typography className={styles.totalLabel}>Самовывоз</Typography>
                <Typography className={styles.totalValue}>Бесплатно</Typography>
              </Box>
              <Divider className={styles.totalDivider} />
              <Box className={styles.totalRow}>
                <Typography className={styles.totalLabelFinal}>
                  Итого
                </Typography>
                <Typography className={styles.totalValueFinal}>
                  {formatCurrency(cart.final_total)}
                </Typography>
              </Box>
              <Button
                className={styles.checkoutBtn}
                onClick={() => navigate("/checkout")}
                disabled={!cart.can_checkout || cart.items.length === 0}
              >
                Оформить заказ
              </Button>
            </Paper>
          </Box>
        </Box>
      </Box>

      <Footer />
    </Box>
  );
}
