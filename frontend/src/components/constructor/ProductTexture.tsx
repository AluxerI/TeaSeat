import type { ConstructorProductSize } from "../../interfaces/giftConstructor";
import { normalizeAssetUrl } from "../../utils/assetUrl";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

/** DOM-текстура над 2.5D-дном: изображения с API, без HTML и подписей. */
export default function ProductTexture({ size, rotated = false }: { size: ConstructorProductSize; rotated?: boolean }) {
  const fallback = size.constructor_role === "sweet" ? "swetty" : size.constructor_role === "general" ? "gift" : "tea";
  const src = normalizeAssetUrl(size.product.image) || `/pages/catalog/details/${fallback}.svg`;
  const ratio = size.size.width_cells / size.size.height_cells;
  return <img className={styles.productTexture} src={src} alt="" draggable={false}
    onError={(event) => { event.currentTarget.onerror = null; if (!event.currentTarget.src.endsWith(`/${fallback}.svg`)) event.currentTarget.src = `/pages/catalog/details/${fallback}.svg`; }}
    style={{ width: rotated ? `${ratio * 100}%` : "100%", height: rotated ? `${100 / ratio}%` : "100%",
      transform: `translate(-50%, -50%) rotate(${rotated ? 90 : 0}deg)` }} />;
}
