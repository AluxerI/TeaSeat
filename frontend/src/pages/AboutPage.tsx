import { Link } from "react-router-dom";
import Header from "../ui/header/header";
import Footer from "../ui/footer/Footer";
import MarketingHeading from "../components/marketing/MarketingHeading";
import styles from "../scss/pages/AboutPage.module.scss";

export default function AboutPage() {
  return <>
    <Header />
    <main className={styles.page}>
      <section className={styles.hero}><MarketingHeading eyebrow="О компании" title="TeaSeat — место для вкуса и подарков" text="Мы хотим, чтобы путь от мысли «хочу хороший чай» до готового заказа оставался понятным и приятным." /></section>
      <section className={styles.values} aria-label="Принципы TeaSeat">
        <article><span>01</span><strong>Выбор без шума</strong><p>Категории, характеристики и отзывы должны помогать принять решение, а не перегружать страницу.</p></article>
        <article><span>02</span><strong>Подарок по‑своему</strong><p>Готовый набор можно выбрать быстро, а собственную композицию — собрать в конструкторе.</p></article>
        <article><span>03</span><strong>Один сценарий</strong><p>Заказ, сборка и доставка связаны в одной системе и не требуют от клиента знать внутреннюю кухню магазина.</p></article>
      </section>
      <section className={styles.note}><div><span>TeaSeat</span><h2>Важна не только витрина</h2></div><p>Мы связываем ассортимент, подарочную сборку и доставку в один понятный опыт — от первого выбора до момента, когда заказ оказывается у получателя.</p></section>
      <div className={styles.actions}><Link to="/catalog">Перейти в каталог →</Link><Link to="/constructor">Собрать подарок →</Link></div>
    </main>
    <Footer />
  </>;
}
