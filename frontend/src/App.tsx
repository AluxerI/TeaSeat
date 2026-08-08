import React, { useEffect, useState } from 'react';
import { PageCategory } from './pages/Category';

import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { ItemApi } from './api/productAPI';

import { FormItem, Item } from './interfaces/clients.api'; 
import { useAsync } from './hooks/useAsync';
import { catalogApi } from './api/catalogAPI';
import { ProductList } from './components/productList';
import { PageCatalog } from './pages/Catalog';
import RegisterPage from './pages/RegisterPage';
import LoginPage from './pages/LoginPage';
import ProfilePage from './pages/ProfilePage';
import OrderPage from './pages/Order';
import CartPage from './pages/CartPage';
import { AuthProvider } from './contexts/AuthContext';
import { SellerProvider } from './contexts/SellerContext';
import LayoutSeller from './layouts/LayoutSeller';
import SellerDashboardPage from './pages/seller/DashboardPage';
import SellerOrdersPage from './pages/seller/OrdersPage';
import SellerOrderWizardPage from './pages/seller/OrderWizardPage';
import SellerOrderDetailPage from './pages/seller/OrderDetailPage';

// Конструктор тянет за собой three.js и @react-three/* — около 600 КБ.
// Статический импорт клал бы их в общий бандл, то есть в загрузку каждой
// страницы, включая офлайн-precache PWA. Грузим только при переходе на роут.
const ConstructorPage = React.lazy(() => import('./pages/ConstructorPage'));


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
  

  
  
  //const chec =recursiveJsonRead(str);
  //console.log(history);
  //history = ''
    
  
  //console.log(check.getProduct(2));
  //check.createProduct(example_body);
  return (
    <>
      <BrowserRouter>
        <AuthProvider>
           <Routes>
             <Route path='/' element={<Navigate to='/catalog' replace />} />
             <Route path='*' element={<Navigate to='/catalog' replace />} />
             <Route path='category' Component={PageCategory}/>
            
            {/**
            <Grid>
                <CatalogItem description='lol' label='xz' picture_button={picture_button_const} picture_part={picture_part_const} price={20}/>
            
                
            </Grid>
             */}

             <Route path='catalog' Component={PageCatalog}/>
             <Route path='register' Component={RegisterPage}/>
             <Route path='login' Component={LoginPage}/>
             <Route path='profile' Component={ProfilePage}/>
             <Route path='order/:id' Component={OrderPage}/>
             <Route path='cart' Component={CartPage}/>
             <Route
               path='constructor'
               element={
                 <React.Suspense fallback={null}>
                   <ConstructorPage />
                 </React.Suspense>
               }
             />
               <Route path="/seller" element={<SellerProvider><LayoutSeller /></SellerProvider>}>
                 <Route index element={<Navigate to="dashboard" replace />} />
                 <Route path="dashboard" element={<SellerDashboardPage />} />
                 <Route path="order/new" element={<SellerOrderWizardPage />} />
                 <Route path="orders" element={<SellerOrdersPage />} />
                 <Route path="orders/:clientOrderId" element={<SellerOrderDetailPage />} />
               </Route>
          </Routes>
        </AuthProvider>
      </BrowserRouter>
    </>
  );
};

export default App;
