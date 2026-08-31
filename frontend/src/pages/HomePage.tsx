import { Link } from "react-router-dom";
import Header from "../ui/header/header";
import Footer from "../ui/footer/Footer";
import ConstructorCta from "../components/ConstructorCta";
import styles from "../scss/pages/MarketingPage.module.scss";

export default function HomePage() {
  return (
    <>
      <Header />
      <main>
        <section className={styles.hero}>
          <div className={styles.heroCopy}>
            <span className={styles.eyebrow}>TeaSeat · чай, кофе и подарки</span>
            <h1>Тёплые вещи для хороших встреч</h1>
            <p>Выбирайте чай и кофе для дома, готовые подарочные наборы или соберите собственный подарок в конструкторе.</p>
            <div className={styles.actions}>
              <Link className={styles.primary} to="/catalog">Перейти в каталог</Link>
              <Link className={styles.secondary} to="/category">Выбрать категорию</Link>
            </div>
          </div>
          <div className={styles.heroArt} aria-hidden="true">
            <span className={styles.heroCup}>TeaSeat</span>
            <span className={styles.heroLeaf}>☘</span>
          </div>
        </section>

        <section className={styles.section}>
          <div className={styles.sectionHeading}><span>Популярные направления</span><h2>Что ищем сегодня?</h2></div>
          <div className={styles.quickGrid}>
            {["Чай", "Кофе", "Подарочные наборы", "Сладости"].map((name) => (
              <Link key={name} to={`/catalog?category=${encodeURIComponent(name)}`} className={styles.quickCard}>
                <strong>{name}</strong><span>Открыть подборку →</span>
              </Link>
            ))}
          </div>
        </section>

        <ConstructorCta />

        <section className={styles.story}>
          <div><span className={styles.eyebrow}>О TeaSeat</span><h2>Не просто витрина</h2></div>
          <p>TeaSeat объединяет понятный каталог, подарочные наборы и удобное оформление заказа — от выбора товара до сборки и доставки.</p>
          <Link to="/about">Подробнее о компании →</Link>
        </section>
      </main>
      <Footer />
    </>
  );
}
