import React, { useEffect, useState } from 'react';

import Header from './ui/header/header';
import Footer  from './ui/footer/footer';
import { Grid } from './components/Grid';
import { CatalogItem, Picture } from './components/catalogItem';

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

const picture_button_const: Picture = {name:"categories/details/img",alt:"image for details", type:'svg'}
const picture_part_const: Picture = {name:"categories/tea_back",alt:"picture of background",type:"svg"}

const App: React.FC = () => {
    
  

  return (
    <>
      <Header />
      
      <Footer />


      <Grid>
          <CatalogItem description='lol' label='xz' picture_button={picture_button_const} picture_part={picture_part_const} price={20}/>

          
      </Grid>
    </>
  );
};

export default App;
