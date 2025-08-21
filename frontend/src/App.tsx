import React, { useEffect, useState } from 'react';
import './App.css';
import Header from './ui/header/header';
import Footer  from './ui/footer/footer';

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
    
  
  return (
    <>
      <Header /><div style={{ padding: 20, color: '#000' }}>
        </div>
      <Footer />
    </>
  );
};

export default App;
