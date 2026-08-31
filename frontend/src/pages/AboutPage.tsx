import Header from "../ui/header/header";
import Footer from "../ui/footer/Footer";
import { Link } from "react-router-dom";
import styles from "../scss/pages/MarketingPage.module.scss";

export default function AboutPage() {
  return (
    <>
      <Header />
      <main className={styles.about}>
        <header className={styles.aboutHero}>
          <span className={styles.eyebrow}>О компании</span>
          <h1>TeaSeat — место для чая, кофе и подарков</h1>
          <p>Мы собираем ассортимент вокруг простых вещей: хороший напиток, понятный выбор и подарок, который можно сделать по‑своему.</p>
        </header>
        <section className={styles.values} aria-label="Принципы TeaSeat">
          <article><strong>Ассортимент</strong><p>Понятные категории и честная информация о товарах.</p></article>
          <article><strong>Подарки</strong><p>Готовые наборы и собственная композиция в конструкторе.</p></article>
          <article><strong>Сервис</strong><p>Заказ, сборка и доставка связаны в одной системе.</p></article>
        </section>
        <section className={styles.aboutBody}>
          <div><h2>Наш подход</h2><p>Показывать товар без лишнего шума, объяснять выбор и сохранять единый опыт от каталога до получения заказа.</p></div>
          <div><h2>Подарки</h2><p>Можно выбрать готовый набор или собрать собственную композицию из коробки, чая и сладостей.</p></div>
        </section>
        <div className={styles.aboutAction}><Link className={styles.primary} to="/catalog">Посмотреть каталог</Link></div>
      </main>
      <Footer />
    </>
  );
}
