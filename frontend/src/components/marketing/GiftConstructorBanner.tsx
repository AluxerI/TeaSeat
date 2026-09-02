import { Link } from "react-router-dom";
import styles from "../../scss/components/GiftConstructorBanner.module.scss";

export default function GiftConstructorBanner() {
  return (
    <section className={styles.wrap} aria-labelledby="gift-constructor-title">
      <Link className={styles.banner} to="/constructor">
        <div className={styles.copy}>
          <span className={styles.eyebrow}>Соберите по‑своему</span>
          <strong id="gift-constructor-title">Конструктор подарков</strong>
          <span className={styles.description}>Выберите коробку, чай и сладости — композицию можно собрать автоматически или расставить самостоятельно.</span>
          <span className={styles.action}>Открыть конструктор <span aria-hidden="true">→</span></span>
        </div>
        <svg className={styles.art} viewBox="0 0 320 210" aria-hidden="true">
          <ellipse className={styles.shadow} cx="164" cy="178" rx="118" ry="18" />
          <g className={styles.contents}>
            <rect className={styles.tea} x="90" y="92" width="70" height="62" rx="6" />
            <ellipse className={styles.leaf} cx="124" cy="120" rx="15" ry="9" />
            <rect className={styles.sweet} x="178" y="91" width="61" height="27" rx="5" />
            <rect className={styles.sweetAlt} x="178" y="127" width="61" height="27" rx="5" />
          </g>
          <path className={styles.box} d="M54 79h214l-17 91H70z" />
          <path className={styles.inside} d="M72 91h178l-12 64H84z" />
          <g className={styles.lid}>
            <path className={styles.lidTop} d="M49 73 84 42h176l14 31z" />
            <path className={styles.lidSide} d="m49 73 10 12h204l11-12z" />
          </g>
        </svg>
      </Link>
    </section>
  );
}
