import { useCallback, useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import {
  Alert,
  Box,
  Button,
  Checkbox,
  CircularProgress,
  Divider,
  FormControlLabel,
  IconButton,
  Paper,
  Typography,
} from "@mui/material";
import ArrowBackIcon from "@mui/icons-material/ArrowBack";
import DeleteOutlineIcon from "@mui/icons-material/DeleteOutline";
import RedeemOutlinedIcon from "@mui/icons-material/RedeemOutlined";
import ShoppingCartOutlinedIcon from "@mui/icons-material/ShoppingCartOutlined";

import Header from "../ui/header/header";
import Footer from "../ui/footer/Footer";
import { useAuth } from "../hooks/useAuth";
import { useCustomerCart } from "../hooks/useCustomerCart";
import { cartApi } from "../api/cartAPI";
import { normalizeAssetUrl } from "../utils/assetUrl";
import { translateError, extractError } from "../utils/translateError";
import { formatMoney } from "../seller/quantity";
import type { Cart } from "../interfaces/cart";
import type { CartQuote, CartSelection } from "../interfaces/checkout";
import styles from "../scss/pages/CartPage.module.scss";

const SELECTION_KEY = "customer_checkout_selection";

// Создаём стабильный выбор и сортируем id. Это исключает лишний quote только
// из-за другого порядка элементов в Set.
function toSelection(items: Set<number>, gifts: Set<number>): CartSelection {
  return {
    cart_item_ids: [...items].sort((a, b) => a - b),
    cart_gift_ids: [...gifts].sort((a, b) => a - b),
  };
}

export default function CartPage() {
  const navigate = useNavigate();
  const { user, loading: authLoading } = useAuth();
  const { replaceCart: replaceSharedCart } = useCustomerCart();
  const [cart, setCart] = useState<Cart | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [quote, setQuote] = useState<CartQuote | null>(null);
  const [quoteLoading, setQuoteLoading] = useState(false);

  // Обычные позиции и подарочные группы выбираются независимо. Компоненты
  // подарка намеренно не попадают в отдельный Set.
  const [selectedItems, setSelectedItems] = useState<Set<number>>(new Set());
  const [selectedGifts, setSelectedGifts] = useState<Set<number>>(new Set());

  // Ключ с префиксом различает одинаковые numeric id товара и подарка.
  const [busy, setBusy] = useState<Set<string>>(new Set());

  const acceptCart = useCallback((next: Cart, preserveSelection = true) => {
    setCart(next);
    // Header и MiniCartDrawer читают тот же снимок, поэтому Badge и итоговая
    // сумма меняются сразу после действий на полной странице корзины.
    replaceSharedCart(next);
    setSelectedItems((current) => {
      const available = new Set(next.items.map((item) => item.id));
      const kept = preserveSelection ? [...current].filter((id) => available.has(id)) : [];
      return new Set(kept.length || preserveSelection ? kept : available);
    });
    setSelectedGifts((current) => {
      const available = new Set(next.gifts.map((gift) => gift.id));
      const kept = preserveSelection ? [...current].filter((id) => available.has(id)) : [];
      return new Set(kept.length || preserveSelection ? kept : available);
    });
  }, [replaceSharedCart]);

  const fetchCart = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const next = await cartApi.getCart();
      setCart(next);
      replaceSharedCart(next);
      setSelectedItems(new Set(next.items.map((item) => item.id)));
      setSelectedGifts(new Set(next.gifts.map((gift) => gift.id)));
    } catch (err) {
      setError(translateError(extractError(err)));
    } finally {
      setLoading(false);
    }
  }, [replaceSharedCart]);

  useEffect(() => {
    if (authLoading) return;
    if (!user) {
      navigate("/login", { replace: true, state: { from: "/cart" } });
      return;
    }
    void fetchCart();
  }, [authLoading, user, navigate, fetchCart]);

  const selection = useMemo(
    () => toSelection(selectedItems, selectedGifts),
    [selectedItems, selectedGifts],
  );
  const selectedCount = selection.cart_item_ids.length + selection.cart_gift_ids.length;

  useEffect(() => {
    if (!cart || selectedCount === 0) {
      setQuote(null);
      return;
    }
    // Небольшой debounce объединяет быстрые клики. active не даёт старому
    // запросу перезаписать расчёт более нового выбора.
    let active = true;
    const timer = window.setTimeout(async () => {
      setQuoteLoading(true);
      try {
        const next = await cartApi.quote(selection);
        if (active) setQuote(next);
      } catch (err) {
        if (active) setError(translateError(extractError(err)));
      } finally {
        if (active) setQuoteLoading(false);
      }
    }, 250);
    return () => {
      active = false;
      window.clearTimeout(timer);
    };
  }, [cart, selection, selectedCount]);

  // Обновления товара и подарка используют одну оболочку: она блокирует только
  // изменяемую строку и принимает свежую корзину из ответа backend.
  const runUpdate = async (key: string, operation: () => Promise<Cart>) => {
    setBusy((current) => new Set(current).add(key));
    setError("");
    try {
      acceptCart(await operation());
    } catch (err) {
      setError(translateError(extractError(err)));
    } finally {
      setBusy((current) => {
        const next = new Set(current);
        next.delete(key);
        return next;
      });
    }
  };

  const allIds = cart ? [...cart.items.map((item) => item.id), ...cart.gifts.map((gift) => gift.id)] : [];
  const allSelected = allIds.length > 0 && selectedCount === allIds.length;

  const toggleAll = () => {
    if (!cart) return;
    setSelectedItems(new Set(allSelected ? [] : cart.items.map((item) => item.id)));
    setSelectedGifts(new Set(allSelected ? [] : cart.gifts.map((gift) => gift.id)));
  };

  const goCheckout = () => {
    if (!selectedCount) return;
    // Route state удобен для перехода, sessionStorage переживает обновление
    // checkout. В обоих местах хранится только выбор, а не цена.
    sessionStorage.setItem(SELECTION_KEY, JSON.stringify(selection));
    navigate("/checkout", { state: { selection } });
  };

  if (loading || authLoading) {
    return <Box className={styles.page}><Header /><Box className={styles.loadingWrap}><CircularProgress /></Box><Footer /></Box>;
  }

  if (error && !cart) {
    return <Box className={styles.page}><Header /><Box className={styles.loadingWrap}><Alert severity="error">{error}</Alert><Button onClick={fetchCart}>Повторить</Button></Box><Footer /></Box>;
  }

  if (!cart || cart.items.length + cart.gifts.length === 0) {
    return <Box className={styles.page}><Header /><Box className={styles.emptyWrap}><ShoppingCartOutlinedIcon className={styles.emptyIcon} /><Typography className={styles.errorText}>Ваша корзина пуста</Typography><Button variant="outlined" className={styles.secondaryButton} onClick={() => navigate("/catalog")}>Перейти в каталог</Button></Box><Footer /></Box>;
  }

  return (
    <Box className={styles.page}>
      <Header />
      <Box className={styles.cartPage}>
        <Box className={styles.backRow}><Button className={styles.backButton} startIcon={<ArrowBackIcon />} onClick={() => navigate("/catalog")} disableRipple>Продолжить покупки</Button></Box>
        <Box className={styles.titleRow}>
          <Typography component="h1" className={styles.pageTitle}>Корзина</Typography>
          <FormControlLabel
            className={styles.selectAll}
            control={<Checkbox checked={allSelected} indeterminate={selectedCount > 0 && !allSelected} onChange={toggleAll} />}
            label="Выбрать всё"
          />
        </Box>
        {error && <Alert severity="error" className={styles.inlineAlert} onClose={() => setError("")}>{error}</Alert>}

        <Box className={styles.cartLayout}>
          <Box component="main" className={styles.cartMain}>
            <Box className={styles.itemsList}>
              {/* Обычные товарные строки можно включать в заказ по одной. */}
              {cart.items.map((item) => {
                const key = `item:${item.id}`;
                const isBusy = busy.has(key);
                const step = Math.max(1, item.sale_step || 1);
                return (
                  <Paper key={key} className={`${styles.itemCard} ${isBusy ? styles.itemUpdating : ""}`} elevation={0}>
                    <Checkbox checked={selectedItems.has(item.id)} onChange={() => setSelectedItems((current) => { const next = new Set(current); next.has(item.id) ? next.delete(item.id) : next.add(item.id); return next; })} inputProps={{ "aria-label": `Выбрать товар ${item.product?.name ?? item.id}` }} />
                    <Box className={styles.itemImage} sx={{ backgroundImage: item.product?.image ? `url(${normalizeAssetUrl(item.product.image)})` : "none", backgroundColor: item.product?.image ? "transparent" : "#f5ead9" }} />
                    <Box className={styles.itemInfo}>
                      <Typography className={styles.itemName}>{item.product?.name || "Товар"}</Typography>
                      <Typography className={styles.itemWeight}>{item.stock_unit === "gram" ? `${item.quantity} г` : `${item.quantity} шт.`}</Typography>
                      <Typography className={styles.itemUnitPrice}>{formatMoney(item.final_unit_price)} за {item.price_unit_quantity} {item.stock_unit === "gram" ? "г" : "шт."}</Typography>
                    </Box>
                    <Box className={styles.itemRight}>
                      <Box className={styles.qtyControls}>
                        <Button className={styles.qtyBtn} onClick={() => runUpdate(key, () => cartApi.updateItemQuantity(item.id, item.quantity - step))} disabled={item.quantity <= step || isBusy}>−</Button>
                        <Typography className={styles.qtyValue}>{item.quantity}</Typography>
                        <Button className={styles.qtyBtn} onClick={() => runUpdate(key, () => cartApi.updateItemQuantity(item.id, item.quantity + step))} disabled={isBusy}>+</Button>
                      </Box>
                      <Typography className={styles.itemTotalPrice}>{formatMoney(item.total_price)}</Typography>
                      {item.saved_amount > 0 && <Typography className={styles.itemSaved}>Экономия {formatMoney(item.saved_amount)}</Typography>}
                      <IconButton className={styles.removeBtn} onClick={() => runUpdate(key, () => cartApi.removeItem(item.id))} disabled={isBusy} size="small" aria-label={`Удалить ${item.product?.name ?? "товар"}`}><DeleteOutlineIcon fontSize="small" /></IconButton>
                    </Box>
                  </Paper>
                );
              })}

              {/* Подарок выбирается и изменяется только целиком. */}
              {cart.gifts.map((gift) => {
                const key = `gift:${gift.id}`;
                const isBusy = busy.has(key);
                return (
                  <Paper key={key} className={`${styles.itemCard} ${styles.giftCard} ${isBusy ? styles.itemUpdating : ""}`} elevation={0}>
                    <Checkbox checked={selectedGifts.has(gift.id)} onChange={() => setSelectedGifts((current) => { const next = new Set(current); next.has(gift.id) ? next.delete(gift.id) : next.add(gift.id); return next; })} inputProps={{ "aria-label": `Выбрать подарок ${gift.name}` }} />
                    <Box className={`${styles.itemImage} ${styles.giftImage}`}><RedeemOutlinedIcon /></Box>
                    <Box className={styles.itemInfo}>
                      <Typography className={styles.itemName}>{gift.name}</Typography>
                      <Typography className={styles.itemWeight}>{gift.items.length} компонентов</Typography>
                      <Box className={styles.giftComponents}>{gift.items.map((item) => <span key={item.id}>{item.product?.name ?? "Товар"}</span>)}</Box>
                    </Box>
                    <Box className={styles.itemRight}>
                      <Box className={styles.qtyControls}>
                        <Button className={styles.qtyBtn} onClick={() => runUpdate(key, () => cartApi.updateGiftQuantity(gift.id, gift.quantity - 1))} disabled={gift.quantity <= 1 || isBusy}>−</Button>
                        <Typography className={styles.qtyValue}>{gift.quantity}</Typography>
                        <Button className={styles.qtyBtn} onClick={() => runUpdate(key, () => cartApi.updateGiftQuantity(gift.id, gift.quantity + 1))} disabled={isBusy}>+</Button>
                      </Box>
                      <Typography className={styles.itemTotalPrice}>{formatMoney(gift.prices.total_price)}</Typography>
                      <IconButton className={styles.removeBtn} onClick={() => runUpdate(key, () => cartApi.removeGift(gift.id))} disabled={isBusy} size="small" aria-label={`Удалить подарок ${gift.name}`}><DeleteOutlineIcon fontSize="small" /></IconButton>
                    </Box>
                  </Paper>
                );
              })}
            </Box>
          </Box>

          {/* Боковая сумма показывает именно серверный quote выбранных строк. */}
          <Box component="aside" className={styles.cartSidebar}>
            <Paper className={styles.sidebarCard} elevation={0}>
              <Typography className={styles.sidebarTitle}>Выбрано: {selectedCount}</Typography>
              <Box className={styles.totalRow}><Typography className={styles.totalLabel}>Товары и подарки</Typography><Typography className={styles.totalValue}>{quote ? formatMoney(quote.products_total + (quote.gift_markup_total ?? 0)) : "—"}</Typography></Box>
              {quote && quote.promotion_discount > 0 && <Box className={styles.totalRow}><Typography className={styles.totalLabel}>Акции</Typography><Typography className={styles.totalDiscount}>−{formatMoney(quote.promotion_discount)}</Typography></Box>}
              {quote && quote.personal_discount > 0 && <Box className={styles.totalRow}><Typography className={styles.totalLabel}>Персональная скидка</Typography><Typography className={styles.totalDiscount}>−{formatMoney(quote.personal_discount)}</Typography></Box>}
              {quote && quote.cart_discount > 0 && <Box className={styles.totalRow}><Typography className={styles.totalLabel}>Скидка корзины</Typography><Typography className={styles.totalDiscount}>−{formatMoney(quote.cart_discount)}</Typography></Box>}
              <Divider className={styles.totalDivider} />
              <Box className={styles.totalRow}><Typography className={styles.totalLabelFinal}>Предварительно</Typography><Typography className={styles.totalValueFinal}>{quoteLoading ? "…" : quote ? formatMoney(quote.final_total) : "—"}</Typography></Box>
              <Button className={styles.checkoutBtn} onClick={goCheckout} disabled={!selectedCount || quoteLoading || !quote}>Оформить выбранное</Button>
              <Typography className={styles.selectionHint}>Невыбранные позиции останутся в корзине после оформления.</Typography>
            </Paper>
          </Box>
        </Box>
      </Box>
      <Footer />
    </Box>
  );
}
