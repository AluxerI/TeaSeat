import { useState } from "react";
import { IconButton } from "@mui/material";
import KeyboardArrowDownRoundedIcon from "@mui/icons-material/KeyboardArrowDownRounded";
import KeyboardArrowUpRoundedIcon from "@mui/icons-material/KeyboardArrowUpRounded";
import VisibilityOutlinedIcon from "@mui/icons-material/VisibilityOutlined";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../hooks/useAuth";
import { useCustomerCart } from "../hooks/useCustomerCart";
import {
  calculateShownPrice,
  getInitialQuantity,
  getMaximumQuantity,
  getProductUnitLabel,
  getSafePriceUnitQuantity,
  getSafeSaleStep,
  isAllowedQuantity,
} from "../utils/productQuantity";
import { extractError, translateError } from "../utils/translateError";
import type { ProductMeasurement } from "../types/productMeasurement";

interface ProductProp {
  productId: number;
  image: string;
  backgroundImage: string;
  label: string;
  brand: string;
  finalPrice: number;
  originalPrice: number;
  discountPercent: number;
  measurement: ProductMeasurement;
  availableQuantity: number;
  isAvailable: boolean;
  onQuickView: () => void;
}

export const ProductItem = ({
  productId,
  image,
  backgroundImage,
  label,
  brand,
  finalPrice,
  originalPrice,
  discountPercent,
  measurement,
  availableQuantity,
  isAvailable,
  onQuickView,
}: ProductProp) => {
  const navigate = useNavigate();
  const { user } = useAuth();
  const { addProduct, pendingActionKey } = useCustomerCart();

  // Все три параметра приходят одним measurement-prop. Так нельзя случайно
  // передать граммы от одного товара и шаг продажи от другого.
  const step = getSafeSaleStep(measurement.saleStep);
  const priceBase = getSafePriceUnitQuantity(measurement.priceUnitQuantity);
  const maximumQuantity = getMaximumQuantity(availableQuantity, step);
  const hasStock = isAvailable && maximumQuantity >= step;
  const [quantity, setQuantity] = useState(() => getInitialQuantity(measurement.stockUnit, step, priceBase, maximumQuantity));
  const [message, setMessage] = useState("");

  const unitLabel = getProductUnitLabel(measurement.stockUnit);
  const shownPrice = calculateShownPrice(finalPrice, quantity, priceBase);
  const adding = pendingActionKey === `add:${productId}`;
  const canDecrease = quantity - step >= step;
  const canIncrease = quantity + step <= maximumQuantity;

  const showLimitMessage = () => {
    setMessage(`Доступно не более ${maximumQuantity} ${unitLabel}`);
  };

  const decreaseQuantity = () => {
    if (!canDecrease) return;
    setMessage("");
    setQuantity((current) => current - step);
  };

  const increaseQuantity = () => {
    // Проверка находится непосредственно в handler: даже программный вызов не
    // сможет увеличить состояние выше доступного остатка.
    if (!canIncrease) {
      showLimitMessage();
      return;
    }
    setMessage("");
    setQuantity((current) => current + step);
  };

  const addToCart = async () => {
    if (!user) {
      navigate("/login", { state: { from: "/catalog" } });
      return;
    }

    // Disabled-кнопки недостаточно: handler повторяет бизнес-проверку перед API.
    // Backend остаётся последней линией защиты при изменившемся остатке.
    const hasValidQuantity = hasStock && isAllowedQuantity(quantity, step, maximumQuantity);
    if (!hasValidQuantity) {
      showLimitMessage();
      return;
    }

    setMessage("");
    try {
      // Provider сохранит ответ backend и сам откроет мини-корзину.
      await addProduct({ product_id: productId, quantity });
    } catch (error) {
      setMessage(translateError(extractError(error)));
    }
  };

  return (
    <article className="product-card">
      <div className="product-card__head">
        <img src={image} alt={label} className="product-card__image" />
        {discountPercent > 0 && <span className="product-card__discount">−{discountPercent}%</span>}
      </div>
      <div
        className="product-card__body"
        style={backgroundImage ? { backgroundImage: `url(${backgroundImage})` } : undefined}
      >
        <section className="section">
          <p className="product-card__brand">{brand}</p>
          <h5 className="label-product">{label}</h5>
          <p className="product-card__stock">Доступно: {maximumQuantity} {unitLabel}</p>

          <div className="gramm-and-price">
            <div className="grams-form">
              <p className="count-gramms">{quantity} {unitLabel}</p>
              <div className="block-buttons">
                <IconButton type="button" onClick={increaseQuantity} disabled={!canIncrease || adding} aria-label={`Увеличить количество ${label}`} size="small">
                  <KeyboardArrowUpRoundedIcon />
                </IconButton>
                <IconButton type="button" onClick={decreaseQuantity} disabled={!canDecrease || adding} aria-label={`Уменьшить количество ${label}`} size="small">
                  <KeyboardArrowDownRoundedIcon />
                </IconButton>
              </div>
              <div className="product-card__prices">
                <p className="price">{shownPrice.toFixed(2)} ₽</p>
                {discountPercent > 0 && <p className="product-card__old-price">{(originalPrice * quantity / priceBase).toFixed(2)} ₽</p>}
              </div>
            </div>
          </div>

          <div className="button-for-buy">
            <button type="button" className="at-cart" onClick={addToCart} disabled={!hasStock || adding}>
              {!hasStock ? "Нет в наличии" : adding ? "Добавляем…" : "В корзину"}
            </button>
            <IconButton
              type="button"
              className="quick-view"
              onClick={onQuickView}
              aria-label={`Быстрый просмотр ${label}`}
            >
              <VisibilityOutlinedIcon />
            </IconButton>
          </div>
          {message && <p className="product-card__message product-card__message--error" role="alert">{message}</p>}
        </section>
      </div>
    </article>
  );
};
