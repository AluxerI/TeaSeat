import type { GiftSizeProfile } from "../../interfaces/giftConstructor";
import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

export interface SceneChoice {
  id: string;
  box: GiftSizeProfile;
  kind: "box" | "simple" | "advanced";
  title: string;
  caption: string;
  ariaLabel: string;
  disabled?: boolean;
  onSelect: () => void;
}

export interface ConstructorSceneProps {
  choices: SceneChoice[];
  selectedId?: string | null;
  label: string;
}

/** Подпись — единственная DOM-кнопка модели: доступна с клавиатуры, без второй карточки под сценой. */
export function SceneChoiceLabel({ choice, selected, onHover }: {
  choice: SceneChoice; selected: boolean; onHover?: (hovered: boolean) => void;
}) {
  return <button type="button" className={styles.sceneChoice} aria-label={choice.ariaLabel}
    aria-pressed={selected} disabled={choice.disabled} onClick={choice.onSelect}
    onFocus={() => onHover?.(true)} onBlur={() => onHover?.(false)}
    onPointerEnter={() => onHover?.(true)} onPointerLeave={() => onHover?.(false)}>
    <strong>{choice.title}</strong><span>{choice.caption}</span>
  </button>;
}

/** Только при загрузке/отказе WebGL. Список работает без канваса и не подменяет данные API. */
export function ConstructorSceneFallback({ choices, selectedId, label }: ConstructorSceneProps) {
  return <div className={styles.sceneFallback} role="group" aria-label={label}>
    {choices.map((choice) => <SceneChoiceLabel key={choice.id} choice={choice} selected={choice.id === selectedId} />)}
  </div>;
}
