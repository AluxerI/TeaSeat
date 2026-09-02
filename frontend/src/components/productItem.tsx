import { useState } from "react";
import { IconButton } from "@mui/material";
import KeyboardArrowDownRoundedIcon from "@mui/icons-material/KeyboardArrowDownRounded";
import KeyboardArrowUpRoundedIcon from "@mui/icons-material/KeyboardArrowUpRounded";
import VisibilityOutlinedIcon from "@mui/icons-material/VisibilityOutlined";
import FavoriteBorderRoundedIcon from "@mui/icons-material/FavoriteBorderRounded";
import FavoriteRoundedIcon from "@mui/icons-material/FavoriteRounded";
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
import { useWishlist } from "../hooks/useWishlist";
import favoriteStyles from "../scss/components/WishlistButton.module.scss";

interface ProductProp {
  productId: number;
  image: string;
  backgroundImage: string;
  label: string;
  brand: string;
  ratingAverage?: number | null;
  reviewsCount?: number;
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
  ratingAverage,
  reviewsCount = 0,
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
  const wishlist = useWishlist(user?.id ?? null);

  // Все три параметра приходят одним measurement-prop. Так нельзя случайно
  // передать граммы от одного товара и шаг продажи от другого.
  const step = getSafeSaleStep(measurement.saleStep);
  const priceBase = getSafePriceUnitQuantity(measurement.priceUnitQuantity);
  const maximumQuantity = getMaximumQuantity(availableQuantity, step);
  const hasStock = isAvailable && maximumQuantity >= step;
  const [quantity, setQuantity] = useState(() => getInitialQuantity(measurement.stockUnit, step, priceBase, maximumQuantity));
  const [message, setMessage] = useState("");
  const [imageReady, setImageReady] = useState(false);
  const [titleExpanded, setTitleExpanded] = useState(false);

  const unitLabel = getProductUnitLabel(measurement.stockUnit);
  const shownPrice = calculateShownPrice(finalPrice, quantity, priceBase);
  const adding = pendingActionKey === `add:${productId}`;
  const canDecrease = quantity - step >= step;
  const canIncrease = quantity + step <= maximumQuantity;
  const favorite = wishlist.has(productId);
  const favoritePending = wishlist.isPending(productId) || Boolean(user && !wishlist.loaded);

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

  const toggleFavorite = async () => {
    if (!user) {
      navigate("/login", { state: { from: "/catalog" } });
      return;
    }
    setMessage("");
    try {
      await wishlist.toggle(productId);
    } catch (error) {
      setMessage(translateError(extractError(error)));
    }
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
      <div className={`product-card__head ${favoriteStyles.head}`}>
        {!imageReady && <span className="product-card__image-loader" aria-hidden="true" />}
        <img
          src={image}
          alt={label}
          className="product-card__image"
          data-ready={imageReady ? "true" : "false"}
          loading="lazy"
          decoding="async"
          onLoad={() => setImageReady(true)}
          onError={() => setImageReady(true)}
        />
        {discountPercent > 0 && <span className="product-card__discount">−{discountPercent}%</span>}
        <IconButton
          type="button"
          className={favoriteStyles.favoriteButton}
          data-active={favorite}
          aria-label={favorite ? `Удалить ${label} из избранного` : `Добавить ${label} в избранное`}
          aria-pressed={favorite}
          title={favorite ? "Убрать из избранного" : "В избранное"}
          disabled={favoritePending}
          onClick={toggleFavorite}
        >
          {favorite ? <FavoriteRoundedIcon fontSize="small" /> : <FavoriteBorderRoundedIcon fontSize="small" />}
        </IconButton>
      </div>
      <div
        className="product-card__body"
        style={backgroundImage ? { backgroundImage: `url(${backgroundImage})` } : undefined}
      >
        <section className="section">
          <p className="product-card__brand">{brand}</p>
          <h5 className="label-product" data-expanded={titleExpanded || undefined}>{label}</h5>
          {label.length > 34 && (
            <button
              type="button"
              className="product-card__title-toggle"
              aria-expanded={titleExpanded}
              onClick={() => setTitleExpanded((current) => !current)}
            >
              {titleExpanded ? "Свернуть" : "Показать название"}
            </button>
          )}
          <div className="product-card__rating" aria-label={ratingAverage == null ? "У товара пока нет оценок" : `Средняя оценка ${ratingAverage.toFixed(1)} из 5, отзывов: ${reviewsCount}`}>
            <span className="product-card__rating-star" aria-hidden="true">★</span>
            <strong>{ratingAverage == null ? "—" : ratingAverage.toFixed(1)}</strong>
            <span>{reviewsCount > 0 ? `Отзывы: ${reviewsCount}` : "Нет отзывов"}</span>
          </div>
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
