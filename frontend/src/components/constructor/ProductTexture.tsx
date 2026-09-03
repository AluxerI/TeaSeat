import type { ConstructorProductSize } from "../../interfaces/giftConstructor";
import { normalizeAssetUrl } from "../../utils/assetUrl";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

/** Упакованный товар над 2.5D-дном. В каталоге остаётся обычное фото товара. */
export default function ProductTexture({ size, rotated = false }: { size: ConstructorProductSize; rotated?: boolean }) {
  const fallback = size.constructor_role === "sweet" ? "swetty" : size.constructor_role === "general" ? "gift" : "tea";
  const roleFallback = `/pages/catalog/details/${fallback}.svg`;
  const productImage = normalizeAssetUrl(size.product.image) || roleFallback;
  const packagingImage = normalizeAssetUrl(size.packaging_template?.image_url);
  const src = packagingImage || productImage;
  const ratio = size.size.width_cells / size.size.height_cells;
  return <span className={styles.productTexture}
    style={{ width: rotated ? `${ratio * 100}%` : "100%", height: rotated ? `${100 / ratio}%` : "100%",
      transform: `translate(-50%, -50%) rotate(${rotated ? 90 : 0}deg)` }}>
    <img className={styles.productTextureImage} src={src} alt="" draggable={false}
      onError={(event) => {
        const image = event.currentTarget;
        if (packagingImage && image.dataset.productFallback !== "true") {
          image.dataset.productFallback = "true";
          image.src = productImage;
          return;
        }
        image.onerror = null;
        if (!image.src.endsWith(`/${fallback}.svg`)) image.src = roleFallback;
      }} />
    {packagingImage && <span className={styles.productTextureLabel}>{size.product.name}</span>}
  </span>;
}
