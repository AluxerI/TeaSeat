import { motion } from "framer-motion";
import type { GiftType } from "../../pages/ConstructorPage";
import type { GiftSizeProfile } from "../../interfaces/giftConstructor";
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
  simpleBox: GiftSizeProfile | null;
  loading: boolean;
  error: string;
}

interface Option {
  type: GiftType;
  label: string;
  description: string;
  badgeClass: string;
  badgeLabel: string;
  contents: string[];
  available: boolean;
}

function buildOptions(simpleBox: GiftSizeProfile | null): Option[] {
  const requirements = simpleBox?.simple_requirements;
  return [{
    type: "simplified",
    label: simpleBox?.name ?? "Упрощённая",
    description: simpleBox
      ? `Компактная коробка с серверной наценкой ${simpleBox.default_markup_amount} ₽.`
      : "Компактная коробка сейчас недоступна.",
    badgeClass: "badgeSimple",
    badgeLabel: simpleBox ? "Доступно" : "Недоступно",
    contents: requirements
      ? [`${requirements.tea_count} чая`, `${requirements.sweet_count} десерт`]
      : ["Состав задаёт backend"],
    available: Boolean(simpleBox),
  },
  {
    type: "complex",
    label: "Усложнённая",
    description: "Большая коробка с открыткой. Роскошный подарочный набор.",
    badgeClass: "badgeComplex",
    badgeLabel: "Скоро",
    contents: ["2.5D-сетка", "свободная раскладка"],
    available: false,
  },
  ];
}

export default function StageZero({
  onSelect,
  selected,
  disabled = false,
  simpleBox,
  loading,
  error,
}: StageZeroProps) {
  const options = buildOptions(simpleBox);
  return (
    <div className={styles.stageZero}>
      {loading && <p className={styles.constructorNotice}>Загружаем доступные коробки…</p>}
      {error && <p className={styles.confirmError}>{error}</p>}
      <div className={styles.giftTypeGrid}>
        {options.map((opt) => {
          const isSelected = selected === opt.type;
          const unavailable = disabled || loading || !opt.available || Boolean(selected);
          return (
            <motion.button
              type="button"
              key={opt.type}
              className={`${styles.giftTypeCard} ${isSelected ? styles.selected : ""}`}
              onClick={() => !unavailable && onSelect(opt.type)}
              disabled={unavailable}
              whileHover={!unavailable ? { y: -4 } : undefined}
              whileTap={!unavailable ? { scale: 0.99 } : undefined}
              transition={{ duration: 0.25, ease: "easeOut" }}
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
            </motion.button>
          );
        })}
      </div>
    </div>
  );
}
