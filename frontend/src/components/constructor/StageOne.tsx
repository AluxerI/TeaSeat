import { motion } from "framer-motion";
import { MOCK_TEAS, type ConstructorItem } from "../../data/constructorMockData";
import styles from "../../scss/pages/ConstructorPage.module.scss";

interface StageOneProps {
  selected: ConstructorItem[];
  onToggle: (item: ConstructorItem) => void;
  /** Запускает 3D-упаковку выбранных чаёв */
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

export default function StageOne({ selected, onToggle, onPack, packing }: StageOneProps) {
  const isSelected = (id: number) => selected.some((t) => t.id === id);
  const slots = [0, 1];
  const ready = selected.length === 2;

  return (
    <div className={styles.selectionStage}>
      <div className={styles.selectionLayout}>
        <div className={styles.selectionMain}>
          <div className={styles.selectionGrid}>
            {MOCK_TEAS.map((tea, i) => {
              const sel = isSelected(tea.id);
              const isFull = (selected.length >= 2 && !sel) || packing;
              return (
                <motion.div
                  key={tea.id}
                  className={`${styles.selectionCard} ${sel ? styles.selected : ""}`}
                  custom={i}
                  variants={cardVariants}
                  initial="hidden"
                  animate="visible"
                  whileHover={!isFull ? { scale: 1.02, y: -3 } : undefined}
                  whileTap={!isFull ? { scale: 0.98 } : undefined}
                  onClick={() => !isFull && onToggle(tea)}
                  style={{
                    opacity: isFull ? 0.45 : 1,
                    cursor: isFull ? "not-allowed" : "pointer",
                  }}
                  layout
                >
                  {sel && (
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
                    <img src={tea.image} alt={tea.name} />
                  </div>

                  <div className={styles.selectionCardName}>{tea.name}</div>
                  <div className={styles.selectionCardDesc}>{tea.description}</div>

                  <div className={styles.selectionCardFooter}>
                    <span className={styles.selectionCardPrice}>{tea.price} ₽</span>
                    <span className={styles.selectionCardWeight}>{tea.weight_grams} г</span>
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
            {slots.map((idx) => {
              const item = selected[idx];
              return (
                <motion.div
                  key={idx}
                  className={styles.boxPreviewItem}
                  layout
                  initial={false}
                  animate={item ? { opacity: 1 } : { opacity: 0.4 }}
                >
                  <div
                    className={`${styles.boxPreviewItemDot} ${
                      item ? styles.tea : styles.empty
                    }`}
                  />
                  <span className={styles.boxPreviewItemName}>
                    {item ? item.name : `Чай ${idx + 1} (выберите)`}
                  </span>
                  {item && (
                    <span className={styles.boxPreviewPrice}>{item.price} ₽</span>
                  )}
                </motion.div>
              );
            })}
          </div>

          <div className={styles.boxPreviewTotal}>
            <span>Итого</span>
            <span>
              {selected.reduce((sum, t) => sum + t.price, 0)} ₽
            </span>
          </div>

          <p style={{ fontSize: 12, color: "#8a7d6f", textAlign: "center", marginTop: 4 }}>
            {selected.length}/2 чая выбрано
          </p>

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
