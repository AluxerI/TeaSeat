import Section from "../UI/Section";
import ProductItem from "./ProductItem";
import styles from "./Products.module.css";

const Products = (props) => {
  let productList = (
    <h2>Товаров не обнаружено. Добавьте хотя бы один товар!</h2>
  );

  if (props.items.length > 0) {
    productList = (
      <ul>
        {props.items.map((product) => (
          <ProductItem key={product.id}>{product.text}</ProductItem>
        ))}
      </ul>
    );
  }

  let content = productList;

  if (props.error) {
    content = <button onClick={props.onFetch}>Попробуйте Ёщё Раз</button>;
  }

  if (props.loading) {
    content = "Загрузка товаров...";
  }

  return (
    <Section>
      <div className={styles.container}>{content}</div>
    </Section>
  );
};

export default Products;
