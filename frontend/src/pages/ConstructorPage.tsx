import { useCallback, useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import Header from "../ui/header/header";
import Footer from "../ui/footer/Footer";
import StageZero from "../components/constructor/StageZero";
import StageOne from "../components/constructor/StageOne";
import StageTwo from "../components/constructor/StageTwo";
import StageThree from "../components/constructor/StageThree";
import ThreeScene from "../components/constructor/ThreeScene";
import type { ScenePhase, GiftType } from "../components/constructor/three/sceneConfig";
import { isScripted } from "../components/constructor/three/sceneConfig";
import type { ConstructorItem } from "../data/constructorMockData";
import styles from "../scss/pages/ConstructorPage.module.scss";

export type { GiftType };
export type ConstructorStage = 0 | 1 | 2 | 3;

const STAGE_LABELS = ["Тип подарка", "Выберите чаи", "Выберите десерт", "Подтверждение"];

/** Подписи, вписанные в саму сцену — они меняются вместе с фазой */
const HERO_CAPTIONS: Record<ConstructorStage, { title: string; sub: string }> = {
  0: {
    title: "Выберите тип набора",
    sub: "Наведите на коробку, чтобы рассмотреть, и нажмите, чтобы собрать",
  },
  1: { title: "Соберите чайную часть", sub: "Выберите два чая и упакуйте их в коробку" },
  2: { title: "Добавьте десерт", sub: "Один десерт к чаю — и набор готов" },
  3: { title: "Всё готово", sub: "Проверьте состав и отправьте подарок в корзину" },
};

const stageVariants = {
  enter: { opacity: 0, y: 16 },
  center: { opacity: 1, y: 0 },
  exit: { opacity: 0, y: -12 },
};

export default function ConstructorPage() {
  const [stage, setStage] = useState<ConstructorStage>(0);
  const [giftType, setGiftType] = useState<GiftType | null>(null);
  const [selectedTeas, setSelectedTeas] = useState<ConstructorItem[]>([]);
  const [selectedSweet, setSelectedSweet] = useState<ConstructorItem | null>(null);

  // ── Состояние 3D ──────────────────────────────────────────────────────────
  const [phase, setPhase] = useState<ScenePhase>({ kind: "chooseType" });
  /** Сколько чаёв уже лежит в коробке — растёт по мере проигрывания анимаций */
  const [packedTeas, setPackedTeas] = useState(0);
  const [packedSweet, setPackedSweet] = useState(false);

  const busy = isScripted(phase);

  // ── Этап 0 ────────────────────────────────────────────────────────────────

  const handleSelectGiftType = useCallback((type: GiftType) => {
    setGiftType(type);
    // Даём коробке доехать до центра, прежде чем передать управление
    // основной модели: подмена объекта в этот момент не видна.
    window.setTimeout(() => setPhase({ kind: "assembleBox" }), 620);
  }, []);

  // ── Этап 1 ────────────────────────────────────────────────────────────────

  const handleToggleTea = useCallback(
    (item: ConstructorItem) => {
      if (busy) return;
      setSelectedTeas((prev) => {
        const exists = prev.find((t) => t.id === item.id);
        if (exists) return prev.filter((t) => t.id !== item.id);
        if (prev.length >= 2) return prev;
        return [...prev, item];
      });
    },
    [busy],
  );

  /** Запускает упаковку выбранных чаёв — по одному, начиная с первого */
  const handlePackTeas = useCallback(() => {
    if (busy || selectedTeas.length !== 2) return;
    setPackedTeas(0);
    setPhase({ kind: "packTea", slot: 0 });
  }, [busy, selectedTeas.length]);

  // ── Этап 2 ────────────────────────────────────────────────────────────────

  const handleSelectSweet = useCallback(
    (item: ConstructorItem) => {
      if (busy) return;
      setSelectedSweet((prev) => (prev?.id === item.id ? null : item));
    },
    [busy],
  );

  const handlePackSweet = useCallback(() => {
    if (busy || !selectedSweet) return;
    setPhase({ kind: "packSweet" });
  }, [busy, selectedSweet]);

  // ── Этап 3 ────────────────────────────────────────────────────────────────

  const handleSeal = useCallback(() => {
    if (busy) return;
    setPhase({ kind: "sealAndFly" });
  }, [busy]);

  // ── Завершение скриптовых фаз ─────────────────────────────────────────────

  const handlePhaseComplete = useCallback((finished: ScenePhase) => {
    switch (finished.kind) {
      case "assembleBox":
        setPhase({ kind: "idleBox" });
        setStage(1);
        break;

      case "packTea": {
        const next = finished.slot + 1;
        setPackedTeas(next);
        if (next < 2) {
          setPhase({ kind: "packTea", slot: 1 });
        } else {
          setPhase({ kind: "idleBox" });
          setStage(2);
        }
        break;
      }

      case "packSweet":
        setPackedSweet(true);
        setPhase({ kind: "idleBox" });
        setStage(3);
        break;

      case "sealAndFly":
        setPhase({ kind: "done" });
        break;

      default:
        break;
    }
  }, []);

  // ── Навигация назад ───────────────────────────────────────────────────────

  const handleBack = useCallback(() => {
    if (busy) return;
    if (stage === 1) {
      setStage(0);
      setGiftType(null);
      setSelectedTeas([]);
      setPackedTeas(0);
      setPhase({ kind: "chooseType" });
    } else if (stage === 2) {
      setStage(1);
      setSelectedTeas([]);
      setPackedTeas(0);
      setPhase({ kind: "idleBox" });
    } else if (stage === 3) {
      setStage(2);
      setSelectedSweet(null);
      setPackedSweet(false);
      setPhase({ kind: "idleBox" });
    }
  }, [stage, busy]);

  const handleReset = useCallback(() => {
    setStage(0);
    setGiftType(null);
    setSelectedTeas([]);
    setSelectedSweet(null);
    setPackedTeas(0);
    setPackedSweet(false);
    setPhase({ kind: "chooseType" });
  }, []);

  const totalPrice = [
    ...selectedTeas.map((t) => t.price),
    selectedSweet ? selectedSweet.price : 0,
  ].reduce((a, b) => a + b, 0);

  const caption = HERO_CAPTIONS[stage];

  return (
    <div className={styles.page}>
      <Header />
      <div className={styles.content}>
        <button
          className={styles.backButton}
          onClick={handleBack}
          disabled={busy}
          style={{ visibility: stage === 0 ? "hidden" : "visible" }}
        >
          ← Назад
        </button>

        <h1 className={styles.pageTitle}>Конструктор подарков</h1>

        {/* Прогресс по этапам */}
        <div className={styles.progressBar}>
          {STAGE_LABELS.map((label, i) => (
            <div key={i} style={{ display: "flex", alignItems: "center", gap: 8 }}>
              <div
                className={`${styles.progressStep} ${
                  i === stage ? styles.active : i < stage ? styles.completed : ""
                }`}
              >
                <div className={styles.progressDot}>{i < stage ? "✓" : i + 1}</div>
                <span>{label}</span>
              </div>
              {i < STAGE_LABELS.length - 1 && (
                <div className={`${styles.progressLine} ${i < stage ? styles.filled : ""}`} />
              )}
            </div>
          ))}
        </div>

        {/* ── Сцена ── */}
        <div className={styles.heroStage}>
          <ThreeScene
            phase={phase}
            giftType={giftType ?? "simplified"}
            packedTeas={packedTeas}
            packedSweet={packedSweet}
            selectedType={giftType}
            onSelectType={handleSelectGiftType}
            onPhaseComplete={handlePhaseComplete}
          />

          <div className={styles.heroOverlay}>
            <div className={styles.heroCaption}>
              <p className={styles.heroCaptionTitle}>{caption.title}</p>
              <p className={styles.heroCaptionSub}>{caption.sub}</p>
            </div>
            {!busy && stage > 0 && (
              <div className={styles.heroHint}>Потяните, чтобы осмотреть коробку</div>
            )}
          </div>
        </div>

        {/* ── Панель этапа ── */}
        <div className={`${styles.stagePanel} ${busy ? styles.stageLocked : ""}`}>
          <AnimatePresence mode="wait">
            {stage === 0 && (
              <motion.div
                key="stage-0"
                variants={stageVariants}
                initial="enter"
                animate="center"
                exit="exit"
                transition={{ duration: 0.3, ease: "easeInOut" }}
              >
                <StageZero onSelect={handleSelectGiftType} selected={giftType} disabled={busy} />
              </motion.div>
            )}

            {stage === 1 && (
              <motion.div
                key="stage-1"
                variants={stageVariants}
                initial="enter"
                animate="center"
                exit="exit"
                transition={{ duration: 0.3, ease: "easeInOut" }}
              >
                <StageOne
                  selected={selectedTeas}
                  onToggle={handleToggleTea}
                  onPack={handlePackTeas}
                  packing={busy}
                />
              </motion.div>
            )}

            {stage === 2 && (
              <motion.div
                key="stage-2"
                variants={stageVariants}
                initial="enter"
                animate="center"
                exit="exit"
                transition={{ duration: 0.3, ease: "easeInOut" }}
              >
                <StageTwo
                  selected={selectedSweet}
                  onSelect={handleSelectSweet}
                  onPack={handlePackSweet}
                  packing={busy}
                />
              </motion.div>
            )}

            {stage === 3 && (
              <motion.div
                key="stage-3"
                variants={stageVariants}
                initial="enter"
                animate="center"
                exit="exit"
                transition={{ duration: 0.3, ease: "easeInOut" }}
              >
                <StageThree
                  giftType={giftType ?? "simplified"}
                  teas={selectedTeas}
                  sweet={selectedSweet!}
                  totalPrice={totalPrice}
                  onSeal={handleSeal}
                  sealed={phase.kind === "done"}
                  sealing={phase.kind === "sealAndFly"}
                  onReset={handleReset}
                />
              </motion.div>
            )}
          </AnimatePresence>
        </div>
      </div>
      <Footer />
    </div>
  );
}
