import { Link } from "react-router-dom";
import styles from "../scss/components/ConstructorCta.module.scss";

export default function ConstructorCta() {
  return (
    <section className={styles.wrap} aria-labelledby="constructor-cta-title">
      <Link className={styles.link} to="/constructor">
        <span className={styles.copy}>
          <span className={styles.eyebrow}>Соберите сами</span>
          <strong id="constructor-cta-title">Конструктор подарков</strong>
          <span>Выберите коробку, чай и сладости — и соберите свой набор.</span>
          <span className={styles.action}>Открыть конструктор →</span>
        </span>
        <svg className={styles.art} viewBox="0 0 240 170" role="img" aria-label="Подарочная коробка">
          <ellipse cx="120" cy="148" rx="84" ry="10" className={styles.shadow} />
          <g className={styles.contents}>
            <rect x="70" y="54" width="44" height="54" rx="8" className={styles.tea} />
            <circle cx="92" cy="73" r="9" className={styles.leaf} />
            <rect x="126" y="65" width="44" height="28" rx="7" className={styles.sweet} />
            <rect x="126" y="99" width="44" height="25" rx="7" className={styles.sweetAlt} />
          </g>
          <path d="M43 80 119 112 197 80v52l-78 30-76-31Z" className={styles.box} />
          <path d="M43 80 119 48l78 32-78 32Z" className={styles.inside} />
          <g className={styles.lid}>
            <path d="M35 65 119 31l86 35-86 35Z" className={styles.lidTop} />
            <path d="M35 65v15l84 34V99Z" className={styles.lidSide} />
            <path d="M205 66v15l-86 33V99Z" className={styles.lidSideAlt} />
          </g>
        </svg>
      </Link>
    </section>
  );
}
