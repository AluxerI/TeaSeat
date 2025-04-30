import { useRef } from "react";

import styles from "./ProductForm.module.css";

const ProductForm = (props) => {
  const productInputRef = useRef();

  const submitHandler = (event) => {
    event.preventDefault();

    const enteredValue = productInputRef.current.value;

    if (enteredValue.trim().length > 0) {
      props.onEnterProduct(enteredValue);
    }
  };

  return (
    <form className={styles.form} onSubmit={submitHandler}>
      <input type="text" ref={productInputRef} />
      <button>
        {props.loading ? "Обработка запроса..." : "Добавить Товар"}
      </button>
    </form>
  );
};

export default ProductForm;
