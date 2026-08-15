import React from 'react';
import { PageCategory } from './pages/Category';

import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
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
import { PickerProvider } from './picker/PickerContext';
import LayoutPicker from './layouts/LayoutPicker';
import PickerQueuePage from './pages/picker/PickerQueuePage';
import PickerOrderPage from './pages/picker/PickerOrderPage';
import PickerTransfersPage from './pages/picker/PickerTransfersPage';
import RequirePermission from './components/RequirePermission';
import { CourierProvider } from './courier/CourierContext';
import LayoutCourier from './layouts/LayoutCourier';
import CourierPWA from './pages/courier/CourierPWA';
import CourierMine from './pages/courier/CourierMine';
import CourierHistory from './pages/courier/CourierHistory';
import CourierDeliveryPage from './pages/courier/CourierDelivery';

// Конструктор тянет за собой three.js и @react-three/* — около 600 КБ.
// Статический импорт клал бы их в общий бандл, то есть в загрузку каждой
// страницы, включая офлайн-precache PWA. Грузим только при переходе на роут.
const ConstructorPage = React.lazy(() => import('./pages/ConstructorPage'));
const App: React.FC = () => {
  return (
    <>
      <BrowserRouter>
        <AuthProvider>
           <Routes>
             <Route path='/' element={<Navigate to='/catalog' replace />} />
             <Route path='*' element={<Navigate to='/catalog' replace />} />
             <Route path='category' Component={PageCategory}/>
            
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
               <Route
                 path="/seller"
                 element={
                   <RequirePermission permission="create seller orders">
                     <SellerProvider><LayoutSeller /></SellerProvider>
                   </RequirePermission>
                 }
               >
                 <Route index element={<Navigate to="dashboard" replace />} />
                 <Route path="dashboard" element={<SellerDashboardPage />} />
                 <Route path="order/new" element={<SellerOrderWizardPage />} />
                 <Route path="orders" element={<SellerOrdersPage />} />
                 <Route path="orders/:clientOrderId" element={<SellerOrderDetailPage />} />
               </Route>
               {/* Секция сборщика (picker PWA): свой каркас + общий контекст.
                   LayoutPicker даёт шапку/меню, PickerProvider — очередь, склад,
                   действия; внутри подставляются страницы по подпути. */}
               <Route
                 path="/picker"
                 element={
                   <RequirePermission permission="view picking orders">
                     <PickerProvider><LayoutPicker /></PickerProvider>
                   </RequirePermission>
                 }
               >
                 <Route index element={<PickerQueuePage mode="queue" />} />
                 <Route path="mine" element={<PickerQueuePage mode="mine" />} />
                 <Route path="transfers" element={<PickerTransfersPage />} />
                 <Route path="orders/:orderId" element={<PickerOrderPage />} />
               </Route>
               {/* Курьерская online-first PWA. Provider хранит единый снимок
                   delivery-объектов, Layout — постоянную мобильную оболочку. */}
               <Route
                 path="/courier"
                 element={
                   <RequirePermission permission="view assigned deliveries">
                     <CourierProvider><LayoutCourier /></CourierProvider>
                   </RequirePermission>
                 }
               >
                 <Route index element={<CourierPWA />} />
                 <Route path="mine" element={<CourierMine />} />
                 <Route path="history" element={<CourierHistory />} />
                 <Route path="deliveries/:deliveryId" element={<CourierDeliveryPage />} />
               </Route>
          </Routes>
        </AuthProvider>
      </BrowserRouter>
    </>
  );
};

export default App;
