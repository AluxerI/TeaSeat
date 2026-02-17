import React, { useEffect, useState } from 'react';


import { CatalogItem, Picture } from './components/catalogItem';
import { PageCategory } from './pages/Category';
import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { ItemApi } from './api/productAPI';

import { FormItem, Item } from './interfaces/clients.api'; 
import { useAsync } from './hooks/useAsync';
import { catalogApi } from './api/catalogAPI';


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


var history="";
function recursiveJsonRead(obj:Record<any,any>,tab:string="",){
  if(obj !== null && typeof obj === "object")
    for(const key in obj){
      history+=(`${tab}${key}:`+'\n')
      recursiveJsonRead(obj[key],tab+" ")
      
    }
  else {
        history+=(`${tab}${obj} (${typeof obj})`+ '\n');
    }
  
}


const App: React.FC = () => {
    
  const check = ItemApi;
  const catalog_api = catalogApi;
  const example_body: FormItem = {
    name:"kek",
    description:"kjfkj",
    price:2,
    user_id:0,
  }
  
  //const data = useAsync(()=>check.getProduct(1),true);
  //const data = useAsync(()=> check.getAllItem())
  //const data = useAsync(()=>catalog_api.getProducts());
  const data = catalog_api.getProducts()
  const dataMeta = catalog_api.getMeta();
  const dataCategory = useAsync(()=>catalogApi.getCategory());
  console.log(dataCategory.data)
  
  //const chec =recursiveJsonRead(str);
  //console.log(history);
  //history = ''
    
  
  //console.log(check.getProduct(2));
  //check.createProduct(example_body);
  return (
    <>
      <BrowserRouter>
        <Routes>
          <Route path='category' Component={PageCategory}/>
          
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
