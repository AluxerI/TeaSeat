import { Link } from "react-router-dom";
import Header from "../ui/header/header";
import Footer from "../ui/footer/Footer";
import ConstructorCta from "../components/ConstructorCta";
import styles from "../scss/pages/CategoryPage.module.scss";

const CATEGORIES = [
  { title: "Чай", description: "Листовой чай и форматы для подарков", image: "/pages/catalog/tea_back.svg" },
  { title: "Кофе", description: "Кофе для дома и подарочных наборов", image: "/pages/catalog/coffe_back.svg" },
  { title: "Подарочные наборы", description: "Готовые сочетания для подарка", image: "/pages/catalog/gift_back.svg" },
  { title: "Сладости", description: "Дополнения к чаю, кофе и подаркам", image: "/pages/catalog/swetty_back.svg" },
] as const;

export default function CategoryLandingPage() {
  return (
  <>
    <Header />
    <main className={styles.page}>
      <header className={styles.heading}>
        <span>Каталог TeaSeat</span>
        <h1>Категории</h1>
        <p>Выберите направление — каталог откроется уже с нужным фильтром.</p>
      </header>
      <section className={styles.grid} aria-label="Категории товаров">
        {CATEGORIES.map((category) => (
          <Link key={category.title} className={styles.card} to={`/catalog?category=${encodeURIComponent(category.title)}`}>
            <img src={category.image} alt="" aria-hidden="true" />
            <span className={styles.cardCopy}>
              <strong>{category.title}</strong>
              <span>{category.description}</span>
              <span className={styles.cardAction}>Смотреть →</span>
            </span>
          </Link>
        ))}
      </section>
      <ConstructorCta />
    </main>
    <Footer />
  </>
  );
}
