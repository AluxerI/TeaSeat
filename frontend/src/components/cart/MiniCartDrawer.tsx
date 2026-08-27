import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Divider,
  Drawer,
  IconButton,
  Typography,
  useMediaQuery,
  useTheme,
} from "@mui/material";
import CloseRoundedIcon from "@mui/icons-material/CloseRounded";
import DeleteOutlineRoundedIcon from "@mui/icons-material/DeleteOutlineRounded";
import RedeemOutlinedIcon from "@mui/icons-material/RedeemOutlined";
import ShoppingCartOutlinedIcon from "@mui/icons-material/ShoppingCartOutlined";
import { useNavigate } from "react-router-dom";
import { useCustomerCart } from "../../hooks/useCustomerCart";
import { normalizeAssetUrl } from "../../utils/assetUrl";
import { getProductUnitLabel } from "../../utils/productQuantity";
import styles from "../../scss/components/MiniCartDrawer.module.scss";

function formatMoney(value: number): string {
  return `${value.toLocaleString("ru-RU", { maximumFractionDigits: 2 })} ₽`;
}
/**
 * Единая мини-корзина для каталога и шапки.
 * На широком экране это боковая панель, на телефоне — нижняя шторка, чтобы до
 * кнопок можно было дотянуться большим пальцем.
 */
export default function MiniCartDrawer() {
  const navigate = useNavigate();
  const theme = useTheme();
  const mobile = useMediaQuery(theme.breakpoints.down("sm"));
  const {
    cart,
    loading,
    error,
    pendingActionKey,
    miniCartOpen,
    lastAddedProductId,
    updateItemQuantity,
    removeItem,
    closeMiniCart,
    clearError,
  } = useCustomerCart();

  const regularItems = cart?.items ?? [];
  const gifts = cart?.gifts ?? [];
  const isEmpty = regularItems.length + gifts.length === 0;

  const goToCart = () => {
    closeMiniCart();
    navigate("/cart");
  };

  return (
    <Drawer
      anchor={mobile ? "bottom" : "right"}
      open={miniCartOpen}
      onClose={closeMiniCart}
      slotProps={{ paper: { className: `${styles.paper} ${mobile ? styles.paperMobile : ""}` } }}
    >
      <Box className={styles.header}>
        <Box>
          <Typography component="h2" className={styles.title}>Корзина</Typography>
          {!isEmpty && (
            <Typography className={styles.subtitle}>
              {regularItems.length + gifts.length} поз.
            </Typography>
          )}
        </Box>
        <IconButton onClick={closeMiniCart} aria-label="Закрыть мини-корзину">
          <CloseRoundedIcon />
        </IconButton>
      </Box>

      <Divider />

      <Box className={styles.content}>
        {error && (
          <Alert severity="error" onClose={clearError} className={styles.alert}>
            {error}
          </Alert>
        )}

        {loading && !cart ? (
          <Box className={styles.center}>
            <CircularProgress size={30} />
            <Typography>Загружаем корзину…</Typography>
          </Box>
        ) : isEmpty ? (
          <Box className={styles.center}>
            <ShoppingCartOutlinedIcon className={styles.emptyIcon} />
            <Typography className={styles.emptyTitle}>Корзина пока пуста</Typography>
            <Typography className={styles.emptyText}>
              Добавьте чай или сладости из каталога.
            </Typography>
          </Box>
        ) : (
          <Box className={styles.items}>
            {regularItems.map((item) => {
              const step = Math.max(1, item.sale_step || 1);
              const updating = pendingActionKey === `update:${item.id}`;
              const removing = pendingActionKey === `remove:${item.id}`;
              const busy = updating || removing;
              const highlighted = item.product_id === lastAddedProductId;
              const unit = getProductUnitLabel(item.stock_unit || item.product.stock_unit);

              return (
                <Box
                  key={item.id}
                  className={`${styles.item} ${highlighted ? styles.itemHighlighted : ""}`}
                >
                  <Box className={styles.imageWrap}>
                    {item.product.image ? (
                      <img
                        src={normalizeAssetUrl(item.product.image)}
                        alt=""
                        className={styles.image}
                      />
                    ) : (
                      <ShoppingCartOutlinedIcon />
                    )}
                  </Box>

                  <Box className={styles.itemInfo}>
                    <Typography className={styles.itemName}>{item.product.name}</Typography>
                    <Typography className={styles.unitPrice}>
                      {formatMoney(item.final_unit_price)} за {item.price_unit_quantity} {unit}
                    </Typography>
                    <Box className={styles.quantity} aria-label={`Количество ${item.product.name}`}>
                      <Button
                        onClick={() => void updateItemQuantity(item.id, item.quantity - step).catch(() => undefined)}
                        disabled={item.quantity <= step || busy}
                        aria-label={`Уменьшить количество ${item.product.name}`}
                      >
                        −
                      </Button>
                      <Typography>{item.quantity} {unit}</Typography>
                      <Button
                        onClick={() => void updateItemQuantity(item.id, item.quantity + step).catch(() => undefined)}
                        disabled={busy}
                        aria-label={`Увеличить количество ${item.product.name}`}
                      >
                        +
                      </Button>
                    </Box>
                  </Box>

                  <Box className={styles.itemEnd}>
                    <IconButton
                      size="small"
                      onClick={() => void removeItem(item.id).catch(() => undefined)}
                      disabled={busy}
                      aria-label={`Удалить ${item.product.name}`}
                    >
                      {removing ? <CircularProgress size={17} /> : <DeleteOutlineRoundedIcon fontSize="small" />}
                    </IconButton>
                    <Typography className={styles.itemTotal}>{formatMoney(item.total_price)}</Typography>
                  </Box>
                </Box>
              );
            })}

            {gifts.map((gift) => (
              <Box key={`gift:${gift.id}`} className={styles.item}>
                <Box className={`${styles.imageWrap} ${styles.giftImage}`}>
                  <RedeemOutlinedIcon />
                </Box>
                <Box className={styles.itemInfo}>
                  <Typography className={styles.itemName}>{gift.name}</Typography>
                  <Typography className={styles.unitPrice}>
                    {gift.items.length} компонентов · {gift.quantity} шт.
                  </Typography>
                </Box>
                <Typography className={styles.itemTotal}>
                  {formatMoney(gift.prices.total_price)}
                </Typography>
              </Box>
            ))}
          </Box>
        )}
      </Box>

      {!isEmpty && cart && (
        <Box className={styles.footer}>
          <Box className={styles.totalRow}>
            <Typography>Итого</Typography>
            <Typography className={styles.total}>{formatMoney(cart.final_total)}</Typography>
          </Box>
          {(cart.promotion_discount + cart.personal_discount + cart.cart_discount) > 0 && (
            <Typography className={styles.saving}>
              Скидка учтена в итоговой стоимости
            </Typography>
          )}
          <Button fullWidth variant="contained" className={styles.primaryButton} onClick={goToCart}>
            Перейти в корзину
          </Button>
          <Button fullWidth className={styles.secondaryButton} onClick={closeMiniCart}>
            Продолжить покупки
          </Button>
        </Box>
      )}
    </Drawer>
  );
}
