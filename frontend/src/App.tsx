import React, { useEffect, useState } from 'react';

import Header from './ui/header/header';
import Footer  from './ui/footer/footer';
import { Grid } from './components/Grid';
import { CatalogItem, Picture } from './components/catalogItem';
import { PageCatalog } from './pages/catalog';
import { BrowserRouter, Route, Routes } from 'react-router-dom';

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
      <BrowserRouter>
        <Routes>
          <Route path='catalog' Component={PageCatalog}/>
          
          {/**
          <Grid>
              <CatalogItem description='lol' label='xz' picture_button={picture_button_const} picture_part={picture_part_const} price={20}/>
          
              
          </Grid>
           */}
        </Routes>
      </BrowserRouter>
    </>
  );
};

export default App;
