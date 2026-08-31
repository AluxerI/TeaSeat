import { useMemo } from "react";
import { Button, CircularProgress, IconButton, Typography } from "@mui/material";
import FavoriteRoundedIcon from "@mui/icons-material/FavoriteRounded";
import OpenInNewRoundedIcon from "@mui/icons-material/OpenInNewRounded";
import { useNavigate } from "react-router-dom";
import { useWishlist } from "../../hooks/useWishlist";
import type { WishlistProduct } from "../../api/wishlistAPI";
import styles from "../../scss/pages/WishlistPanel.module.scss";

function assetUrl(value: string | null | undefined): string {
  if (!value) return "/pages/catalog/details/tea.svg";
  return value.replace(/^https?:\/\/[^/]+/, "") || "/pages/catalog/details/tea.svg";
}

function brandName(product: WishlistProduct): string {
  if (typeof product.brand === "string") return product.brand;
  return product.brand?.name ?? "TeaSeat";
}

function numberValue(value: number | string | null | undefined): number | null {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : null;
}

function priceOf(product: WishlistProduct): number | null {
  return numberValue(product.final_price ?? product.price);
}

export default function WishlistPanel({ userId }: { userId: number }) {
  const navigate = useNavigate();
  const wishlist = useWishlist(userId);
  const entries = useMemo(
    () => wishlist.entries.filter((entry) => entry.product && Number.isFinite(entry.product.id)),
    [wishlist.entries],
  );

  if (wishlist.loading && !wishlist.loaded) {
    return <div className={styles.loading}><CircularProgress size={28} /><span>Загружаем избранное…</span></div>;
  }

  if (wishlist.error && entries.length === 0) {
    return (
      <div className={styles.state} role="alert">
        <FavoriteRoundedIcon />
        <Typography>{wishlist.error}</Typography>
        <Button variant="outlined" onClick={() => void wishlist.refresh()}>Повторить</Button>
      </div>
    );
  }

  if (entries.length === 0) {
    return (
      <div className={styles.state}>
        <FavoriteRoundedIcon />
        <Typography component="h2">Пока ничего не сохранено</Typography>
        <Typography>Нажмите на сердце в карточке товара — он появится здесь.</Typography>
        <Button variant="contained" onClick={() => navigate("/catalog")}>Перейти в каталог</Button>
      </div>
    );
  }

  return (
    <section className={styles.wrap} aria-label="Избранные товары">
      <div className={styles.meta}>
        <span>{entries.length} {entries.length === 1 ? "товар" : "товаров"}</span>
        {wishlist.loading && <span>Обновляем…</span>}
      </div>
      <div className={styles.grid}>
        {entries.map((entry) => {
          const product = entry.product;
          const rating = numberValue(product.rating_average);
          const reviews = numberValue(product.reviews_count) ?? 0;
          const price = priceOf(product);
          const pending = wishlist.isPending(product.id);
          return (
            <article key={entry.id || product.id} className={styles.card}>
              <div className={styles.imageWrap}>
                <img src={assetUrl(product.image ?? product.image_url)} alt={product.name} loading="lazy" />
                <IconButton
                  className={styles.remove}
                  aria-label={`Удалить ${product.name} из избранного`}
                  title="Убрать из избранного"
                  disabled={pending}
                  onClick={() => void wishlist.toggle(product.id).catch(() => undefined)}
                >
                  <FavoriteRoundedIcon />
                </IconButton>
              </div>
              <div className={styles.body}>
                <span className={styles.brand}>{brandName(product)}</span>
                <Typography component="h3" className={styles.name}>{product.name}</Typography>
                <div className={styles.rating} aria-label={rating === null ? "Нет оценок" : `Средняя оценка ${rating.toFixed(1)} из 5`}>
                  <span aria-hidden="true">★</span>
                  <strong>{rating === null ? "—" : rating.toFixed(1)}</strong>
                  <span>{reviews > 0 ? `${reviews} отзывов` : "Нет отзывов"}</span>
                </div>
                <div className={styles.footer}>
                  <strong>{price === null ? "Цена в каталоге" : `${price.toFixed(2)} ₽`}</strong>
                  <Button size="small" endIcon={<OpenInNewRoundedIcon />} onClick={() => navigate("/catalog")}>
                    В каталог
                  </Button>
                </div>
              </div>
            </article>
          );
        })}
      </div>
      {wishlist.error && <Typography className={styles.inlineError} role="alert">{wishlist.error}</Typography>}
    </section>
  );
}
