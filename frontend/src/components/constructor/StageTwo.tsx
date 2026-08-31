import { motion } from "framer-motion";
import type { ConstructorProductSize } from "../../interfaces/giftConstructor";
import { normalizeAssetUrl } from "../../utils/assetUrl";
import { constructorItemPrice, constructorItemQuantity } from "../../utils/giftConstructor";
import styles from "../../scss/pages/ConstructorPage.module.scss";

interface StageTwoProps {
  options: ConstructorProductSize[];
  selected: ConstructorProductSize[];
  requiredCount: number;
  onAdd: (item: ConstructorProductSize) => void;
  onRemove: (item: ConstructorProductSize) => void;
  onPack: () => void;
  packing: boolean;
}
const cardVariants = {
  hidden: { opacity: 0, y: 20 },
  visible: (i: number) => ({
    opacity: 1,
    y: 0,
    transition: { delay: i * 0.08, duration: 0.35, ease: "easeOut" as const },
  }),
};

export default function StageTwo({
  options,
  selected,
  requiredCount,
  onAdd,
  onRemove,
  onPack,
  packing,
}: StageTwoProps) {
  const selectedCount = (id: number) => selected.filter((item) => item.id === id).length;
  const ready = selected.length === requiredCount;

  return (
    <div className={styles.selectionStage}>
      <div className={styles.selectionLayout}>
        <div className={styles.selectionMain}>
          <div className={styles.selectionGrid}>
            {options.map((sweet, index) => {
              const count = selectedCount(sweet.id);
              const image = normalizeAssetUrl(sweet.product.image) || "/pages/catalog/details/swetty.svg";
              return (
                <motion.article
                  key={sweet.id}
                  className={`${styles.selectionCard} ${count > 0 ? styles.selected : ""}`}
                  custom={index}
                  variants={cardVariants}
                  initial="hidden"
                  animate="visible"
                  whileHover={!packing ? { scale: 1.02, y: -3 } : undefined}
                  layout
                >
                  {count > 0 && <div className={styles.selectionCheck}>{count}</div>}
                  <div className={styles.selectionCardImage}>
                    <img src={image} alt="" />
                  </div>
                  <div className={styles.selectionCardName}>{sweet.product.name}</div>
                  <div className={styles.selectionCardDesc}>{sweet.label}</div>
                  <div className={styles.selectionCardFooter}>
                    <span className={styles.selectionCardPrice}>{constructorItemPrice(sweet)} ₽</span>
                    <span className={styles.selectionCardWeight}>{constructorItemQuantity(sweet)}</span>
                  </div>
                  <div className={styles.selectionQuantity}>
                    <button
                      type="button"
                      onClick={() => onRemove(sweet)}
                      disabled={packing || count === 0}
                      aria-label={`Убрать ${sweet.product.name}`}
                    >
                      −
                    </button>
                    <span aria-label={`Выбрано ${sweet.product.name}: ${count}`}>{count}</span>
                    <button
                      type="button"
                      onClick={() => onAdd(sweet)}
                      disabled={packing || selected.length >= requiredCount}
                      aria-label={`Добавить ${sweet.product.name}`}
                    >
                      +
                    </button>
                  </div>
                </motion.article>
              );
            })}
          </div>
        </div>

        <div className={styles.boxPreviewPanel}>
          <div className={styles.boxPreviewTitle}>Ваша коробка</div>
          <div className={styles.boxPreviewItems}>
            {Array.from({ length: requiredCount }, (_, index) => {
              const item = selected[index];
              return (
                <div key={index} className={styles.boxPreviewItem}>
                  <div className={`${styles.boxPreviewItemDot} ${item ? styles.sweet : styles.empty}`} />
                  <span className={styles.boxPreviewItemName}>
                    {item ? item.product.name : `Десерт ${index + 1} (выберите)`}
                  </span>
                  {item && <span className={styles.boxPreviewPrice}>{constructorItemPrice(item)} ₽</span>}
                </div>
              );
            })}
          </div>
          <p className={styles.selectionCounter}>{selected.length}/{requiredCount} десерта выбрано</p>
          <button
            className={styles.btnPrimary}
            onClick={onPack}
            disabled={!ready || packing}
            style={{ width: "100%", marginTop: 8 }}
          >
            {packing ? "Упаковываем…" : "Упаковать в коробку"}
          </button>
        </div>
      </div>
    </div>
  );
}
