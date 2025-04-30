import styles from "./ProductItem.module.css";

const ProductItem = (props) => {
  return <li className={styles.product}>{props.children}</li>;
};

export default ProductItem;
