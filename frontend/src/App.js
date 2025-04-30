import React, { useEffect, useState } from "react";

import Products from "./components/Products/Products";
import NewProduct from "./components/NewProduct/NewProduct";
import useHttp from "./components/hooks/use-http";
import PhpRequest from "./components/PhpRequest";
function App() {
  const [products, setProducts] = useState([]);

  const httpRequestData = useHttp();

  const { isLoading, error, sendHttpRequest: fetchProducts } = httpRequestData;

  /*
  const fetchProducts = async (productText) => {
    setIsLoading(true);
    setError(null);
    try {
      const response = await fetch(
        "https://react-course-http-9e97b-default-rtdb.firebaseio.com/products.json"
      );

      if (!response.ok) {
        throw new Error("Ошибка запроса.");
      }

      const data = await response.json();

      const loadedProducts = [];

      for (const productKey in data) {
        loadedProducts.push({ id: productKey, text: data[productKey].text });
      }

      setProducts(loadedProducts);
    } catch (err) {
      setError(err.message || "Что-то пошло не так...");
    }
    setIsLoading(false);
  };
  */

  useEffect(() => {
    const manageProducts = (productsData) => {
      const loadedProducts = [];

      for (const productKey in productsData) {
        loadedProducts.push({
          id: productKey,
          text: productsData[productKey].text,
        });
      }

      setProducts(loadedProducts);
    };

   
  }, []);

  const productAddHandler = (product) => {
    setProducts((prevProducts) => prevProducts.concat(product));
  };

  return (
    <React.Fragment>
      <NewProduct onAddProduct={productAddHandler} />
      <Products
        items={products}
        loading={isLoading}
        error={error}
        onFetch={fetchProducts}
      />
      <PhpRequest></PhpRequest>
    </React.Fragment>
  );
}

export default App;
