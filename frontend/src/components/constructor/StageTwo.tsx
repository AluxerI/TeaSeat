import { motion } from "framer-motion";
import { MOCK_SWEETS, type ConstructorItem } from "../../data/constructorMockData";
import styles from "../../scss/pages/ConstructorPage.module.scss";

interface StageTwoProps {
  selected: ConstructorItem | null;
  onSelect: (item: ConstructorItem) => void;
  /** Запускает 3D-упаковку десерта */
  onPack: () => void;
  /** Анимация уже идёт — карточки и кнопка недоступны */
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

export default function StageTwo({ selected, onSelect, onPack, packing }: StageTwoProps) {
  return (
    <div className={styles.selectionStage}>
      <div className={styles.selectionLayout}>
        <div className={styles.selectionMain}>
          <div className={styles.selectionGrid}>
            {MOCK_SWEETS.map((sweet, i) => {
              const isSelected = selected?.id === sweet.id;
              return (
                <motion.div
                  key={sweet.id}
                  className={`${styles.selectionCard} ${isSelected ? styles.selected : ""}`}
                  custom={i}
                  variants={cardVariants}
                  initial="hidden"
                  animate="visible"
                  whileHover={!packing ? { scale: 1.02, y: -3 } : undefined}
                  whileTap={!packing ? { scale: 0.98 } : undefined}
                  onClick={() => !packing && onSelect(sweet)}
                  style={{
                    opacity: packing ? 0.45 : 1,
                    cursor: packing ? "not-allowed" : "pointer",
                  }}
                  layout
                >
                  {isSelected && (
                    <motion.div
                      className={styles.selectionCheck}
                      initial={{ scale: 0 }}
                      animate={{ scale: 1 }}
                      exit={{ scale: 0 }}
                      transition={{ type: "spring", stiffness: 400, damping: 15 }}
                    >
                      ✓
                    </motion.div>
                  )}

                  <div className={styles.selectionCardImage}>
                    <img src={sweet.image} alt={sweet.name} />
                  </div>

                  <div className={styles.selectionCardName}>{sweet.name}</div>
                  <div className={styles.selectionCardDesc}>{sweet.description}</div>

                  <div className={styles.selectionCardFooter}>
                    <span className={styles.selectionCardPrice}>{sweet.price} ₽</span>
                    <span className={styles.selectionCardWeight}>{sweet.weight_grams} г</span>
                  </div>
                </motion.div>
              );
            })}
          </div>
        </div>

        {/* Box preview */}
        <div className={styles.boxPreviewPanel}>
          <div className={styles.boxPreviewTitle}>Ваша коробка</div>
          <div className={styles.boxPreviewItems}>
            <div className={styles.boxPreviewItem}>
              <div className={`${styles.boxPreviewItemDot} ${styles.sweet}`} />
              <span className={styles.boxPreviewItemName}>
                {selected ? selected.name : "Десерт (выберите)"}
              </span>
              {selected && (
                <span className={styles.boxPreviewPrice}>{selected.price} ₽</span>
              )}
            </div>
          </div>

          <p style={{ fontSize: 12, color: "#8a7d6f", textAlign: "center", marginTop: 4 }}>
            {selected ? "1/1 десерт выбран" : "Выберите 1 десерт"}
          </p>

          <button
            className={styles.btnPrimary}
            onClick={onPack}
            disabled={!selected || packing}
            style={{ width: "100%", marginTop: 8 }}
          >
            {packing ? "Упаковываем…" : "Упаковать в коробку"}
          </button>
        </div>
      </div>
    </div>
  );
}
