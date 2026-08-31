import { motion } from "framer-motion";
import type { ConstructorProductSize } from "../../interfaces/giftConstructor";
import { normalizeAssetUrl } from "../../utils/assetUrl";
import { constructorItemPrice, constructorItemQuantity } from "../../utils/giftConstructor";
import styles from "../../scss/pages/ConstructorPage.module.scss";

interface StageOneProps {
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

export default function StageOne({
  options,
  selected,
  requiredCount,
  onAdd,
  onRemove,
  onPack,
  packing,
}: StageOneProps) {
  const selectedCount = (id: number) => selected.filter((item) => item.id === id).length;
  const ready = selected.length === requiredCount;
  const slots = Array.from({ length: requiredCount }, (_, index) => index);

  return (
    <div className={styles.selectionStage}>
      <div className={styles.selectionLayout}>
        <div className={styles.selectionMain}>
          <div className={styles.selectionGrid}>
            {options.map((tea, index) => {
              const count = selectedCount(tea.id);
              const image = normalizeAssetUrl(tea.product.image) || "/pages/catalog/details/tea.svg";
              return (
                <motion.article
                  key={tea.id}
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
                  <div className={styles.selectionCardName}>{tea.product.name}</div>
                  <div className={styles.selectionCardDesc}>{tea.label}</div>
                  <div className={styles.selectionCardFooter}>
                    <span className={styles.selectionCardPrice}>{constructorItemPrice(tea)} ₽</span>
                    <span className={styles.selectionCardWeight}>{constructorItemQuantity(tea)}</span>
                  </div>
                  <div className={styles.selectionQuantity}>
                    <button
                      type="button"
                      onClick={() => onRemove(tea)}
                      disabled={packing || count === 0}
                      aria-label={`Убрать ${tea.product.name}`}
                    >
                      −
                    </button>
                    <span aria-label={`Выбрано ${tea.product.name}: ${count}`}>{count}</span>
                    <button
                      type="button"
                      onClick={() => onAdd(tea)}
                      disabled={packing || selected.length >= requiredCount}
                      aria-label={`Добавить ${tea.product.name}`}
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
            {slots.map((index) => {
              const item = selected[index];
              return (
                <motion.div
                  key={index}
                  className={styles.boxPreviewItem}
                  layout
                  initial={false}
                  animate={item ? { opacity: 1 } : { opacity: 0.4 }}
                >
                  <div className={`${styles.boxPreviewItemDot} ${item ? styles.tea : styles.empty}`} />
                  <span className={styles.boxPreviewItemName}>
                    {item ? item.product.name : `Чай ${index + 1} (выберите)`}
                  </span>
                  {item && (
                    <span className={styles.boxPreviewPrice}>{constructorItemPrice(item)} ₽</span>
                  )}
                </motion.div>
              );
            })}
          </div>
          <div className={styles.boxPreviewTotal}>
            <span>Предварительно</span>
            <span>{selected.reduce((sum, item) => sum + constructorItemPrice(item), 0)} ₽</span>
          </div>
          <p className={styles.selectionCounter}>{selected.length}/{requiredCount} чая выбрано</p>
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
