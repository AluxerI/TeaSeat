import { motion } from "framer-motion";
import type { GiftType } from "../../pages/ConstructorPage";
import styles from "../../scss/pages/ConstructorPage.module.scss";

/**
 * Этап 0 — текстовая часть выбора типа набора.
 *
 * Сами коробки живут в 3D-сцене выше: там они кренятся к курсору, проявляют
 * цвет и складываются по клику. Здесь остаются только состав, цена и бейджи —
 * дублировать коробку ещё и в SVG значило бы показывать два разных подарка
 * на одном экране.
 */

interface StageZeroProps {
  onSelect: (type: GiftType) => void;
  selected: GiftType | null;
  disabled?: boolean;
}

interface Option {
  type: GiftType;
  label: string;
  description: string;
  badgeClass: string;
  badgeLabel: string;
  contents: string[];
}

const OPTIONS: Option[] = [
  {
    type: "simplified",
    label: "Упрощённая",
    description: "Компактная коробка. Лёгкий подарок, который всегда уместен.",
    badgeClass: "badgeSimple",
    badgeLabel: "Хит",
    contents: ["2 чая", "1 десерт"],
  },
  {
    type: "complex",
    label: "Усложнённая",
    description: "Большая коробка с открыткой. Роскошный подарочный набор.",
    badgeClass: "badgeComplex",
    badgeLabel: "Премиум",
    contents: ["3 чая", "2 десерта", "открытка"],
  },
];

export default function StageZero({ onSelect, selected, disabled = false }: StageZeroProps) {
  return (
    <div className={styles.stageZero}>
      <div className={styles.giftTypeGrid}>
        {OPTIONS.map((opt) => {
          const isSelected = selected === opt.type;
          return (
            <motion.div
              key={opt.type}
              className={`${styles.giftTypeCard} ${isSelected ? styles.selected : ""}`}
              onClick={() => !disabled && !selected && onSelect(opt.type)}
              whileHover={!disabled && !selected ? { y: -4 } : undefined}
              whileTap={!disabled && !selected ? { scale: 0.99 } : undefined}
              transition={{ duration: 0.25, ease: "easeOut" }}
              style={{ cursor: disabled || selected ? "default" : "pointer" }}
            >
              <span className={`${styles.giftTypeBadge} ${styles[opt.badgeClass]}`}>
                {opt.badgeLabel}
              </span>

              <div className={styles.giftTypeLabel}>{opt.label}</div>
              <div className={styles.giftTypeDescription}>{opt.description}</div>

              <div className={styles.giftTypeContents}>
                {opt.contents.map((c) => (
                  <span key={c} className={styles.giftTypeChip}>
                    {c}
                  </span>
                ))}
              </div>
            </motion.div>
          );
        })}
      </div>
    </div>
  );
}
