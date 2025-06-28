import React, { useEffect, useState } from "react";

const PhpRequest = () => {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const response = await fetch("http://localhost:8000/item/3", {
          method: 'GET',
          headers: {
            'Content-Type': "application/json" // Добавьте токен, если требуется
          }
        });
        if (!response.ok) {
          throw new Error("Network response was not ok");
        }
        const result = await response.json();
        setData(result);
      } catch (error) {
        setError(error);
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, []);

  if (loading) {
    return <div>Loading...</div>;
  }

  if (error) {
    return <div>Error: {error.message}</div>;
  }

  return (
    <div>
      <h1>Product</h1>
      <p>Название: {item.name}</p>
      <p>Цена: {item.price}</p>
      <p>Описание: {item.description}</p>
      <p>На складе: {item.stock}</p>
      <p>Склады: {item.warehouse.join(', ')}</p>
    </div>
  );
};

export default PhpRequest;