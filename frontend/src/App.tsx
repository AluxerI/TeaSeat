import React, { useEffect, useState } from 'react';
import './App.css';
import Header from './components/Headers/header';

interface Data {
  id: number;
  name: string;
  price: number;
  description: string;
  stock: number;
  warehouses: number[];
}
interface ApiResponse {
  data: Data;
}

const App: React.FC = () => {
  const [item, setItem] = useState<Data | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);

  useEffect(() => {
    const fetchItem = async () => {
      try {
        const res = await fetch('http://localhost:8000/item/3');
        console.log('Fetch status:', res.status, res.headers.get('Content-Type'));
        if (!res.ok) throw new Error(`HTTP ${res.status}`);


        const json: ApiResponse = await res.json();
        
        setItem(json.data);
      } catch (e) {
        console.error('Fetch error:', e);
        setError(e instanceof Error ? e : new Error('Unknown error'));
      } finally {
        setLoading(false);
      }
    };
    fetchItem();
  }, []);

  if (loading)   return <div>Loading…</div>;
  if (error)     return <div style={{ color: 'red' }}>Error: {error.message}</div>;
  if (!item)     return <div>No item data</div>;

  // Выведем всё «сырое» перед тем, как форматировать
  return (
    <><Header /><div style={{ padding: 20, color: '#000' }}>
      <h1>🔍 RAW DATA</h1>
      <pre>{JSON.stringify(item, null, 2)}</pre>

      <h1>🏷 Форматированный вывод</h1>
      <p><strong>Name:</strong> {item.name}</p>
      <p><strong>Price:</strong> {item.price} ₽</p>
      <p><strong>Description:</strong> {item.description}</p>
      <p><strong>Stock:</strong> {item.stock}</p>

    </div></>
  );
};

export default App;
