import { lazy, Suspense, useEffect, useRef, useState, type ReactNode } from "react";
import { flushSync } from "react-dom";
import type { GiftSizeProfile } from "../../interfaces/giftConstructor";
import { supportsSimple } from "../../utils/simpleGiftValidation";
import { ConstructorSceneFallback, type SceneChoice } from "./ConstructorSceneChoice";
import choiceStyles from "../../scss/pages/ConstructorChoiceScene.module.scss";

export type ConstructorMode = "simple" | "advanced";
export { supportsSimple } from "../../utils/simpleGiftValidation";
const ConstructorBoxScene = lazy(() => import("./ConstructorBoxScene"));

interface PendingChoice<T> {
  id: string;
  value: T;
}

interface ViewTransitionHandle {
  finished: Promise<void>;
}

type TransitionDocument = Document & {
  startViewTransition?: (update: () => void | Promise<void>) => ViewTransitionHandle;
};

function supportsNativeViewTransition(): boolean {
  if (typeof document === "undefined") return false;
  return typeof (document as TransitionDocument).startViewTransition === "function";
}

/**
 * Для первого -> второго шага используем shared-element transition: снимок
 * выбранной коробки из шага 1 превращается в единственную коробку шага 2.
 * На старом браузере переход выполняется сразу, без искусственной задержки.
 */
function useChoiceTransition<T>(commit: (value: T) => void) {
  const [pending, setPending] = useState<PendingChoice<T> | null>(null);
  const pendingRef = useRef<PendingChoice<T> | null>(null);
  const commitRef = useRef(commit);
  const firstFrame = useRef<number | null>(null);
  const secondFrame = useRef<number | null>(null);
  commitRef.current = commit;
  const native = supportsNativeViewTransition();

  useEffect(() => () => {
    if (firstFrame.current !== null) window.cancelAnimationFrame(firstFrame.current);
    if (secondFrame.current !== null) window.cancelAnimationFrame(secondFrame.current);
  }, []);

  const choose = (id: string, value: T) => {
    if (pendingRef.current) return;
    const reduced = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false;
    if (reduced || !native) {
      commitRef.current(value);
      return;
    }

    const next = { id, value };
    pendingRef.current = next;
    setPending(next);

    // Два кадра не создают видимую паузу: React только успевает назначить
    // выбранной модели shared view-transition-name до old snapshot.
    firstFrame.current = window.requestAnimationFrame(() => {
      secondFrame.current = window.requestAnimationFrame(() => {
        const current = pendingRef.current;
        const start = (document as TransitionDocument).startViewTransition;
        if (!current || !start) {
          if (current) commitRef.current(current.value);
          return;
        }
        try {
          const transition = start.call(document, () => {
            flushSync(() => {
              pendingRef.current = null;
              setPending(null);
              commitRef.current(current.value);
            });
          });
          transition.finished.catch(() => undefined);
        } catch {
          pendingRef.current = null;
          setPending(null);
          commitRef.current(current.value);
        }
      });
    });
  };

  return { pendingId: pending?.id ?? null, choose, native };
}

function ChoiceTransitionStage({ native, children }: { native: boolean; children: ReactNode }) {
  return <div className={choiceStyles.transitionStage} data-native={native || undefined}>{children}</div>;
}

function ChoiceHeading({ title, description }: { title: string; description?: string }) {
  return <header className={choiceStyles.heading}>
    <span className={choiceStyles.eyebrow}>Конструктор подарка</span>
    <h2>{title}</h2>
    {description && <p>{description}</p>}
  </header>;
}

export function ConstructorModePicker({ box, onSelect }: { box: GiftSizeProfile; onSelect: (mode: ConstructorMode) => void }) {
  const transition = useChoiceTransition(onSelect);
  const choices: SceneChoice[] = [
    { id: "simple", kind: "simple", box, title: "Быстрая сборка", ariaLabel: "Быстрая сборка",
      caption: supportsSimple(box)
        ? "Выберите чай и сладости — мы проверим вместимость и аккуратно уложим всё автоматически."
        : "Для этой коробки быстрая сборка недоступна — выберите «Свою композицию».",
      disabled: !supportsSimple(box), onSelect: () => transition.choose("simple", "simple") },
    { id: "advanced", kind: "advanced", box, title: "Своя композиция", ariaLabel: "Своя композиция",
      caption: "Выбирайте товары сами, задавайте им место и поворачивайте внутри коробки.",
      onSelect: () => transition.choose("advanced", "advanced") },
  ];
  return <section className={choiceStyles.section} aria-label="Выбор конструктора">
    <ChoiceHeading title="Как хотите собрать подарок?" description="Коробка уже выбрана — теперь только способ наполнения." />
    <ChoiceTransitionStage native={transition.native}>
      <Suspense fallback={<ConstructorSceneFallback choices={choices} selectedId={transition.pendingId} label="Способы сборки" />}>
        <ConstructorBoxScene choices={choices} selectedId={transition.pendingId} label="Способы сборки в 3D" />
      </Suspense>
    </ChoiceTransitionStage>
  </section>;
}

export function ConstructorBoxPicker({ boxes, selectedId, onSelect, cellSizeMm }: {
  boxes: GiftSizeProfile[]; selectedId: number | null; onSelect: (box: GiftSizeProfile) => void; cellSizeMm?: number | null;
}) {
  const transition = useChoiceTransition(onSelect);
  const choices: SceneChoice[] = boxes.map((box) => ({
    id: String(box.id), kind: "box", box, title: box.name, ariaLabel: "Выбрать коробку " + box.name,
    caption: box.width_cells * (cellSizeMm || 1) + " × " + box.height_cells * (cellSizeMm || 1) + (cellSizeMm ? " мм" : " клеток"),
    onSelect: () => transition.choose(String(box.id), box),
  }));
  const scene = { choices, selectedId: transition.pendingId ?? (selectedId === null ? null : String(selectedId)), label: "Коробки в 3D" };
  return <section className={choiceStyles.section} aria-label="Выбор коробки">
    <ChoiceHeading title="Выберите коробку" description="Выбранная коробка плавно перейдёт с вами к наполнению." />
    <ChoiceTransitionStage native={transition.native}>
      <Suspense fallback={<ConstructorSceneFallback {...scene} />}>
        <ConstructorBoxScene {...scene} />
      </Suspense>
    </ChoiceTransitionStage>
  </section>;
}
