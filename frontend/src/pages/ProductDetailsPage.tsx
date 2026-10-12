import { useEffect, useMemo, useState } from "react";
import { useParams, Link, useNavigate } from "react-router-dom";
import { catalogApi } from "../api/catalogAPI";
import { productReviewsApi, type ProductReview } from "../api/productReviewsAPI";
import { useAuth } from "../hooks/useAuth";
import { useCustomerCart } from "../hooks/useCustomerCart";
import { useWishlist } from "../hooks/useWishlist";
import { useAsync } from "../hooks/useAsync";
import { calculateShownPrice, getInitialQuantity, getMaximumQuantity, getProductUnitLabel, getSafePriceUnitQuantity, getSafeSaleStep, isAllowedQuantity } from "../utils/productQuantity";
import type { ProductMeasurement } from "../types/productMeasurement";
import Header from "../ui/header/header";
import Footer from "../ui/footer/Footer";
import "../scss/pages/ProductDetailsPage.module.scss";

function Stars({ value, max = 5 }: { value: number; max?: number }) {
  return (
    <span className="pd-stars">
      {Array.from({ length: max }).map((_, i) => (
        <svg key={i} width="18" height="18" viewBox="0 0 16 16" fill={i < value ? "#F59E0B" : "#E5E7EB"}>
          <path d="M8 1l1.854 3.756L14 5.528l-3 2.924.708 4.126L8 10.5l-3.708 2.078L5 8.452 2 5.528l4.146-.772L8 1z" />
        </svg>
      ))}
    </span>
  );
}

export default function ProductDetailsPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user } = useAuth();
  const { addProduct, pendingActionKey } = useCustomerCart();
  const wishlist = useWishlist(user?.id ?? null);

  const product = useAsync(() => catalogApi.getProducts().then((products) => products?.find((p) => p.id === Number(id))), true);
  const reviewsData = useAsync(() => productReviewsApi.getReviews(Number(id)), true);

  const [quantity, setQuantity] = useState(1);
  const [reviewRating, setReviewRating] = useState(5);
  const [reviewText, setReviewText] = useState("");
  const [reviewSubmitting, setReviewSubmitting] = useState(false);
  const [reviewSuccess, setReviewSuccess] = useState(false);
  const [reviewError, setReviewError] = useState("");

  const p = product.data;
  const measurement: ProductMeasurement | undefined = p ? {
    stockUnit: p.stock_unit,
    saleStep: p.sale_step,
    priceUnitQuantity: p.price_unit_quantity,
  } : undefined;

  const step = measurement ? getSafeSaleStep(measurement.saleStep) : 1;
  const priceBase = measurement ? getSafePriceUnitQuantity(measurement.priceUnitQuantity) : 1;
  const maxQty = p ? getMaximumQuantity(p.total_quantity, step) : 1;
  const hasStock = p?.is_available && maxQty >= step;
  const unitLabel = measurement ? getProductUnitLabel(measurement.stockUnit) : "шт";
  const shownPrice = p ? calculateShownPrice(p.final_price, quantity, priceBase) : 0;
  const favorite = p ? wishlist.has(p.id) : false;
  const adding = p ? pendingActionKey === `add:${p.id}` : false;

  const reviews = reviewsData.data?.reviews ?? [];
  const avgRating = reviewsData.data?.average_rating ?? p?.rating_average ?? 0;
  const reviewsCount = reviewsData.data?.reviews_count ?? p?.reviews_count ?? 0;

  useEffect(() => { setQuantity(1); }, [id]);

  const handleAddToCart = async () => {
    if (!user) { navigate("/login", { state: { from: `/product/${id}` } }); return; }
    if (!p || !hasStock) return;
    const validQty = isAllowedQuantity(quantity, step, maxQty);
    if (!validQty) return;
    try {
      await addProduct({ product_id: p.id, quantity });
    } catch {}
  };

  const handleToggleFavorite = async () => {
    if (!user) { navigate("/login", { state: { from: `/product/${id}` } }); return; }
    if (!p) return;
    try { await wishlist.toggle(p.id); } catch {}
  };

  const handleSubmitReview = async () => {
    if (!user) { navigate("/login", { state: { from: `/product/${id}` } }); return; }
    if (!reviewText.trim()) { setReviewError("Введите текст отзыва."); return; }
    setReviewSubmitting(true);
    setReviewError("");
    try {
      await productReviewsApi.createReview(Number(id), reviewRating, reviewText.trim());
      setReviewSuccess(true);
      setReviewText("");
      setReviewRating(5);
      reviewsData.execute();
    } catch {
      setReviewError("Не удалось отправить отзыв. Попробуйте позже.");
    } finally {
      setReviewSubmitting(false);
    }
  };

  if (product.loading) return <><Header /><main className="pd-page"><div className="pd-loading">Загружаем товар…</div></main><Footer /></>;
  if (!p) return <><Header /><main className="pd-page"><div className="pd-empty">Товар не найден.</div></main><Footer /></>;

  return (
    <>
      <Header />
      <main className="pd-page">
        <nav className="pd-breadcrumb">
          <Link to="/catalog">Каталог</Link>
          <span>/</span>
          <span>{p.category_path.category}</span>
          {p.category_path.subcategory && <><span>/</span><span>{p.category_path.subcategory}</span></>}
        </nav>

        <div className="pd-layout">
          <div className="pd-gallery">
            <img src={p.main_image} alt={p.name} className="pd-image" />
          </div>

          <div className="pd-info">
            <p className="pd-brand">{p.brand}</p>
            <h1 className="pd-title">{p.name}</h1>

            <div className="pd-rating" onClick={() => document.getElementById("reviews")?.scrollIntoView({ behavior: "smooth" })}>
              <Stars value={avgRating} />
              <span>{avgRating > 0 ? avgRating.toFixed(1) : "—"}</span>
              <span className="pd-reviews-link">{reviewsCount > 0 ? `${reviewsCount} отзывов` : "Нет отзывов"}</span>
            </div>

            <div className="pd-price-block">
              <span className="pd-price">{shownPrice.toFixed(2)} ₽</span>
              {p.discount_percent > 0 && (
                <>
                  <span className="pd-old-price">{(p.original_price * quantity / priceBase).toFixed(2)} ₽</span>
                  <span className="pd-discount">−{p.discount_percent}%</span>
                </>
              )}
            </div>

            <p className="pd-stock">{hasStock ? `В наличии: ${maxQty} ${unitLabel}` : "Нет в наличии"}</p>

            {p.inventory && p.inventory.length > 0 && <div className="pd-warehouses">
              {p.inventory.filter((w) => w.quantity > 0).length === 0
                ? <span className="pd-warehouse-empty">Нет на складах</span>
                : p.inventory.filter((w) => w.quantity > 0).map((w) => <span key={w.warehouse_id} className="pd-warehouse-item">
                    {w.warehouse_name}: {w.quantity.toLocaleString("ru-RU")} {unitLabel}
                  </span>)}
            </div>}

            {p.description && <p className="pd-description">{p.description}</p>}

            <div className="pd-qty-row">
              <button onClick={() => setQuantity((q) => Math.max(step, q - step))} disabled={quantity <= step || adding}>−</button>
              <span>{quantity} {unitLabel}</span>
              <button onClick={() => { if (quantity + step <= maxQty) setQuantity((q) => q + step); }} disabled={quantity + step > maxQty || adding}>+</button>
            </div>

            <div className="pd-actions">
              <button className="pd-add-cart" onClick={handleAddToCart} disabled={!hasStock || adding}>
                {!hasStock ? "Нет в наличии" : adding ? "Добавляем…" : "В корзину"}
              </button>
              <button className={`pd-fav ${favorite ? "pd-fav--active" : ""}`} onClick={handleToggleFavorite} aria-label={favorite ? "Убрать из избранного" : "В избранное"}>
                {favorite ? "♥" : "♡"}
              </button>
            </div>
          </div>
        </div>

        <section className="pd-reviews" id="reviews">
          <h2>Отзывы ({reviewsCount})</h2>

          {reviews.length === 0 && !reviewsData.loading && (
            <p className="pd-no-reviews">Пока нет отзывов. Будьте первым!</p>
          )}

          <div className="pd-reviews-list">
            {reviews.map((r) => (
              <div key={r.id} className="pd-review-card">
                <div className="pd-review-header">
                  <strong>{r.user_name}</strong>
                  <Stars value={r.rating} />
                  <time>{new Date(r.created_at).toLocaleDateString("ru-RU")}</time>
                </div>
                <p>{r.text}</p>
              </div>
            ))}
          </div>

          {user && (
            <div className="pd-write-review">
              <h3>Оставить отзыв</h3>
              {reviewSuccess ? (
                <p className="pd-review-success">Спасибо! Ваш отзыв отправлен.</p>
              ) : (
                <>
                  <div className="pd-review-stars">
                    <span>Ваша оценка:</span>
                    {[1, 2, 3, 4, 5].map((r) => (
                      <button key={r} className={`pd-star-btn ${r <= reviewRating ? "pd-star-btn--active" : ""}`} onClick={() => setReviewRating(r)} type="button">★</button>
                    ))}
                  </div>
                  <textarea value={reviewText} onChange={(e) => setReviewText(e.target.value)} placeholder="Расскажите о вашем опыте…" rows={4} />
                  {reviewError && <p className="pd-review-error" role="alert">{reviewError}</p>}
                  <button className="pd-submit-review" onClick={handleSubmitReview} disabled={reviewSubmitting}>
                    {reviewSubmitting ? "Отправляем…" : "Отправить отзыв"}
                  </button>
                </>
              )}
            </div>
          )}
        </section>
      </main>
      <Footer />
    </>
  );
}
