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
    
  
  return (
    <><Header /><div style={{ padding: 20, color: '#000' }}>
      
      
    </div></>
  );
};

export default App;
