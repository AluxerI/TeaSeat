import { Link } from "react-router-dom";
import Header from "../ui/header/header";
import Footer from "../ui/footer/Footer";
import MarketingHeading from "../components/marketing/MarketingHeading";
import GiftConstructorBanner from "../components/marketing/GiftConstructorBanner";
import styles from "../scss/pages/HomePage.module.scss";

const DIRECTIONS = [
  ["Чай", "/pages/catalog/tea_back.svg"],
  ["Кофе", "/pages/catalog/coffe_back.svg"],
  ["Подарочные наборы", "/pages/catalog/gift_back.svg"],
  ["Сладости", "/pages/catalog/swetty_back.svg"],
] as const;

export default function HomePage() {
  return <>
    <Header />
    <main className={styles.page}>
      <section className={styles.hero}>
        <div className={styles.heroCopy}>
          <span className={styles.eyebrow}>TeaSeat · чай, кофе и подарки</span>
          <h1>Вкус, который хочется разделить</h1>
          <p>Выберите чай или кофе для себя, найдите готовый подарок или соберите собственную композицию.</p>
          <div className={styles.heroActions}><Link className={styles.primary} to="/catalog">Перейти в каталог</Link><Link className={styles.secondary} to="/category">Выбрать категорию</Link></div>
        </div>
        <div className={styles.heroVisual} aria-hidden="true">
          <span className={styles.heroCard} data-card="tea"><img src="/pages/catalog/tea_back.svg" alt="" /></span>
          <span className={styles.heroCard} data-card="coffee"><img src="/pages/catalog/coffe_back.svg" alt="" /></span>
        </div>
      </section>

      <section className={styles.section}>
        <MarketingHeading level={2} eyebrow="Ассортимент" title="Выберите своё направление" text="Четыре понятные точки входа вместо перегруженной витрины." action={{ label: "Все категории", to: "/category" }} />
        <div className={styles.directionGrid}>
          {DIRECTIONS.map(([title, image]) => <Link className={styles.direction} key={title} to={`/catalog?category=${encodeURIComponent(title)}`}>
            <img src={image} alt="" aria-hidden="true" /><span><strong>{title}</strong><small>Открыть подборку →</small></span>
          </Link>)}
        </div>
      </section>

      <GiftConstructorBanner />

      <section className={styles.story}>
        <MarketingHeading level={2} eyebrow="О TeaSeat" title="От выбора до готового подарка" text="Каталог, сборка и доставка связаны в одном сценарии — без необходимости разбираться во внутренних процессах магазина." action={{ label: "О компании", to: "/about" }} />
        <div className={styles.storyPoints}><span><b>01</b> Понятный каталог</span><span><b>02</b> Собственная композиция</span><span><b>03</b> Сборка и доставка</span></div>
      </section>
    </main>
    <Footer />
  </>;
}
