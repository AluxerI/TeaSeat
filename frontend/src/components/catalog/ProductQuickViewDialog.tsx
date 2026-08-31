import { useEffect, useMemo, useState } from "react";
import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogContent,
  IconButton,
  Typography,
} from "@mui/material";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import CloseRoundedIcon from "@mui/icons-material/CloseRounded";
import RemoveRoundedIcon from "@mui/icons-material/RemoveRounded";
import ShoppingBagOutlinedIcon from "@mui/icons-material/ShoppingBagOutlined";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../../hooks/useAuth";
import { useCustomerCart } from "../../hooks/useCustomerCart";
import type { Product } from "../../interfaces/catalog";
import { normalizeAssetUrl } from "../../utils/assetUrl";
import {
  calculateShownPrice,
  getInitialQuantity,
  getMaximumQuantity,
  getProductUnitLabel,
  getSafePriceUnitQuantity,
  getSafeSaleStep,
  isAllowedQuantity,
} from "../../utils/productQuantity";
import { extractError, translateError } from "../../utils/translateError";
import styles from "../../scss/components/ProductQuickViewDialog.module.scss";

interface ProductQuickViewDialogProps {
  product: Product | null;
  open: boolean;
  onClose: () => void;
}
function formatMoney(value: number): string {
  return `${value.toLocaleString("ru-RU", { maximumFractionDigits: 2 })} ₽`;
}

/**
 * Краткий просмотр помогает выбрать количество и купить товар, но намеренно
 * не заменяет будущую полную страницу: здесь нет состава, всей галереи,
 * отзывов, складов и подробных условий скидок.
 */
export default function ProductQuickViewDialog({
  product,
  open,
  onClose,
}: ProductQuickViewDialogProps) {
  const navigate = useNavigate();
  const { user } = useAuth();
  const { addProduct, pendingActionKey } = useCustomerCart();
  const [quantity, setQuantity] = useState(0);
  const [error, setError] = useState("");

  const measurement = useMemo(() => {
    if (!product) return null;
    const step = getSafeSaleStep(product.sale_step);
    const priceBase = getSafePriceUnitQuantity(product.price_unit_quantity);
    const maximum = getMaximumQuantity(product.total_quantity, step);
    return {
      step,
      priceBase,
      maximum,
      unitLabel: getProductUnitLabel(product.stock_unit),
    };
  }, [product]);

  // Каждый новый товар начинает со своего корректного количества. Состояние
  // предыдущего товара не должно случайно попасть в следующий запрос.
  useEffect(() => {
    if (!product || !measurement) return;
    setQuantity(getInitialQuantity(
      product.stock_unit,
      measurement.step,
      measurement.priceBase,
      measurement.maximum,
    ));
    setError("");
  }, [measurement, product]);

  if (!product || !measurement) return null;

  const hasStock = product.is_available && measurement.maximum >= measurement.step;
  const adding = pendingActionKey === `add:${product.id}`;
  const canDecrease = quantity - measurement.step >= measurement.step;
  const canIncrease = quantity + measurement.step <= measurement.maximum;
  const finalTotal = calculateShownPrice(product.final_price, quantity, measurement.priceBase);
  const originalTotal = calculateShownPrice(product.original_price, quantity, measurement.priceBase);

  const increase = () => {
    if (!canIncrease) {
      setError(`Доступно не более ${measurement.maximum} ${measurement.unitLabel}`);
      return;
    }
    setError("");
    setQuantity((current) => current + measurement.step);
  };

  const decrease = () => {
    if (!canDecrease) return;
    setError("");
    setQuantity((current) => current - measurement.step);
  };

  const addToCart = async () => {
    if (!user) {
      onClose();
      navigate("/login", { state: { from: "/catalog" } });
      return;
    }

    if (!hasStock || !isAllowedQuantity(quantity, measurement.step, measurement.maximum)) {
      setError(`Доступно не более ${measurement.maximum} ${measurement.unitLabel}`);
      return;
    }

    setError("");
    try {
      await addProduct({ product_id: product.id, quantity });
      // Provider уже открыл мини-корзину. Сначала закрываем Dialog, чтобы два
      // модальных слоя не конкурировали за фокус, особенно на телефоне.
      onClose();
    } catch (reason) {
      setError(translateError(extractError(reason)));
    }
  };

  return (
    <Dialog
      open={open}
      onClose={adding ? undefined : onClose}
      fullWidth
      maxWidth="sm"
      aria-labelledby="product-quick-view-title"
      slotProps={{ paper: { className: styles.paper } }}
    >
      <IconButton
        className={styles.closeButton}
        onClick={onClose}
        disabled={adding}
        aria-label="Закрыть быстрый просмотр"
      >
        <CloseRoundedIcon />
      </IconButton>

      <DialogContent className={styles.content}>
        <Box className={styles.imageColumn}>
          <Box className={styles.imageWrap}>
            {product.main_image ? (
              <img
                src={normalizeAssetUrl(product.main_image)}
                alt={product.name}
                className={styles.image}
              />
            ) : (
              <ShoppingBagOutlinedIcon className={styles.imagePlaceholder} />
            )}
            {product.discount_percent > 0 && (
              <Chip label={`−${product.discount_percent}%`} className={styles.discount} />
            )}
          </Box>
        </Box>

        <Box className={styles.infoColumn}>
          {product.brand && <Typography className={styles.brand}>{product.brand}</Typography>}
          <Typography id="product-quick-view-title" component="h2" className={styles.title}>
            {product.name}
          </Typography>

          {product.description && (
            <Typography className={styles.description}>{product.description}</Typography>
          )}

          <Typography className={hasStock ? styles.stock : styles.outOfStock}>
            {hasStock
              ? `В наличии: ${measurement.maximum} ${measurement.unitLabel}`
              : "Нет в наличии"}
          </Typography>

          <Box className={styles.priceRow}>
            <Typography className={styles.price}>{formatMoney(finalTotal)}</Typography>
            {product.discount_percent > 0 && (
              <Typography className={styles.oldPrice}>{formatMoney(originalTotal)}</Typography>
            )}
          </Box>

          <Box className={styles.quantityBlock}>
            <Typography className={styles.quantityLabel}>Количество</Typography>
            <Box className={styles.quantityControl}>
              <IconButton
                onClick={decrease}
                disabled={!canDecrease || adding}
                aria-label={`Уменьшить количество ${product.name}`}
              >
                <RemoveRoundedIcon />
              </IconButton>
              <Typography aria-live="polite">
                {quantity} {measurement.unitLabel}
              </Typography>
              <IconButton
                onClick={increase}
                disabled={!canIncrease || adding}
                aria-label={`Увеличить количество ${product.name}`}
              >
                <AddRoundedIcon />
              </IconButton>
            </Box>
          </Box>

          {error && <Alert severity="warning" className={styles.alert}>{error}</Alert>}

          <Button
            fullWidth
            variant="contained"
            className={styles.addButton}
            onClick={() => void addToCart()}
            disabled={!hasStock || adding}
            startIcon={adding ? <CircularProgress color="inherit" size={18} /> : <ShoppingBagOutlinedIcon />}
          >
            {!hasStock ? "Нет в наличии" : adding ? "Добавляем…" : "В корзину"}
          </Button>
        </Box>
      </DialogContent>
    </Dialog>
  );
}
