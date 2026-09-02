import { Link } from "react-router-dom";
import Header from "../ui/header/header";
import Footer from "../ui/footer/Footer";
import MarketingHeading from "../components/marketing/MarketingHeading";
import GiftConstructorBanner from "../components/marketing/GiftConstructorBanner";
import styles from "../scss/pages/CategoryLandingPage.module.scss";

const CATEGORIES = [
  { title: "Чай", description: "Листовой чай, купажи и форматы для дома и подарков.", image: "/pages/catalog/tea_back.svg" },
  { title: "Кофе", description: "Зерно и кофейные позиции для спокойных домашних ритуалов.", image: "/pages/catalog/coffe_back.svg" },
  { title: "Подарочные наборы", description: "Готовые сочетания, которые можно сразу отправить получателю.", image: "/pages/catalog/gift_back.svg" },
  { title: "Сладости", description: "Дополнения к чаю, кофе и подарочным композициям.", image: "/pages/catalog/swetty_back.svg" },
] as const;

export default function CategoryLandingPage() {
  return <>
    <Header />
    <main className={styles.page}>
      <div className={styles.heading}><MarketingHeading eyebrow="Каталог TeaSeat" title="Категории" text="Выберите направление — каталог откроется уже с нужной подборкой." /></div>
      <section className={styles.grid} aria-label="Категории товаров">
        {CATEGORIES.map((category) => <Link key={category.title} className={styles.card} to={`/catalog?category=${encodeURIComponent(category.title)}`}>
          <span className={styles.visual}><img src={category.image} alt="" aria-hidden="true" /></span>
          <span className={styles.copy}>
            <strong>{category.title}</strong>
            <span>{category.description}</span>
            <span className={styles.action}>Смотреть <span aria-hidden="true">→</span></span>
          </span>
        </Link>)}
      </section>
      <GiftConstructorBanner />
    </main>
    <Footer />
  </>;
}
