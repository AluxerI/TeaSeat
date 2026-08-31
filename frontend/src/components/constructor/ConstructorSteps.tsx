import styles from "../../scss/pages/ConstructorWorkspace.module.scss";

const steps = ["Коробка", "Наполнение", "Проверка", "Корзина"];
export default function ConstructorSteps({ current, onBack, locked = false }: {
  current: number;
  onBack?: (step: number) => void;
  locked?: boolean;
}) {
  return <nav className={styles.steps} aria-label="Шаги конструктора">
    <ol>{steps.map((label, index) => <li key={label} aria-current={index === current ? "step" : undefined}>
      <button type="button" disabled={locked || !onBack || index >= current} onClick={() => onBack?.(index)}>
        <span aria-hidden="true">{index < current ? "✓" : index + 1}</span>{label}
      </button>
    </li>)}</ol>
    <p>Подарок собирается здесь. Доставка и оформление заказа — после перехода в корзину.</p>
  </nav>;
}
