import React from 'react';
import HomePage from './pages/HomePage';
import AboutPage from './pages/AboutPage';
import CategoryLandingPage from './pages/CategoryLandingPage';

import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { PageCatalog } from './pages/Catalog';
import RegisterPage from './pages/RegisterPage';
import LoginPage from './pages/LoginPage';
import ProfilePage from './pages/ProfilePage';
import OrderPage from './pages/Order';
import CartPage from './pages/CartPage';
import CheckoutPage from './pages/CheckoutPage';
import { AuthProvider } from './contexts/AuthContext';
import { CustomerCartProvider } from './contexts/CustomerCartContext';
import MiniCartDrawer from './components/cart/MiniCartDrawer';
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
import { ManagerProvider } from './contexts/ManagerContext'; // контекст кабинета менеджера

// Конструктор тянет за собой three.js и @react-three/* — около 600 КБ.
// Статический импорт клал бы их в общий бандл, то есть в загрузку каждой
// страницы, включая офлайн-precache PWA. Грузим только при переходе на роут.
const ConstructorPage = React.lazy(() => import('./pages/ConstructorPage'));
// Manager — большой online-only кабинет. Отделяем его страницы от клиентского
// и offline PWA-бандла; браузер загрузит код только после входа в /manager.
// Каждая страница кабинета лениво импортируется (React.lazy) отдельно.
const LayoutManager = React.lazy(() => import('./layouts/LayoutManager')); // каркас кабинета (шапка/сайдбар/навигация)
const ManagerOrdersPage = React.lazy(() => import('./pages/manager/ManagerOrdersPage')); // список заказов
const ManagerOrderDetailPage = React.lazy(() => import('./pages/manager/ManagerOrderDetailPage')); // детали заказа
const ManagerIssuesPage = React.lazy(() => import('./pages/manager/ManagerIssuesPage')); // список проблем комплектации
const ManagerIssueDetailPage = React.lazy(() => import('./pages/manager/ManagerIssueDetailPage')); // детали проблемы
const ManagerRequestsPage = React.lazy(() => import('./pages/manager/ManagerRequestsPage')); // список обращений клиентов
const ManagerRequestDetailPage = React.lazy(() => import('./pages/manager/ManagerRequestDetailPage')); // детали обращения
const ManagerModerationPage = React.lazy(() => import('./pages/manager/ManagerModerationPage')); // модерация отзывов и оценок
const ManagerReviewDetailPage = React.lazy(() => import('./pages/manager/ManagerReviewDetailPage')); // детали отзыва на товар
const ManagerFeedbackDetailPage = React.lazy(() => import('./pages/manager/ManagerFeedbackDetailPage')); // детали оценки заказа
const App: React.FC = () => {
  return (
    <>
      <BrowserRouter>
        <AuthProvider>
          <CustomerCartProvider>
           <Routes>
             <Route path='*' element={<Navigate to='/catalog' replace />} />
             <Route path='/' Component={HomePage}/>
             <Route path='category' Component={CategoryLandingPage}/>
             <Route path='about' Component={AboutPage}/>
             <Route path='catalog' Component={PageCatalog}/>
             <Route path='register' Component={RegisterPage}/>
             <Route path='login' Component={LoginPage}/>
             <Route path='profile' Component={ProfilePage}/>
             <Route path='order/:id' Component={OrderPage}/>
             {/* Покупатель собирает выбор в корзине, оформляет его на checkout
                 и после успешного POST переходит на созданный заказ. */}
             <Route path='cart' Component={CartPage}/>
             <Route path='checkout' Component={CheckoutPage}/>
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
               {/* Manager — online-first рабочий кабинет. Provider держит
                   только общие badges/точку/Snackbar; данные страниц загружают
                   отдельные hooks, чтобы большие очереди не перерисовывали друг друга. */}
               <Route
                 path="/manager" // корень кабинета менеджера
                 element={
                   // Доступ только с правом «view manager orders».
                   <RequirePermission permission="view manager orders">
                     {/* Пока lazy-страница грузится — ничего не рендерим. */}
                     <React.Suspense fallback={null}>
                       <ManagerProvider><LayoutManager /></ManagerProvider> {/* общий контекст + каркас кабинета */}
                     </React.Suspense>
                   </RequirePermission>
                 }
               >
                 <Route index element={<Navigate to="orders" replace />} /> {/* корень → список заказов */}
                 <Route path="orders" element={<ManagerOrdersPage />} /> {/* список заказов */}
                 <Route path="orders/:orderId" element={<ManagerOrderDetailPage />} /> {/* детали заказа */}
                 <Route path="issues" element={<ManagerIssuesPage />} /> {/* список проблем */}
                 <Route path="issues/:issueId" element={<ManagerIssueDetailPage />} /> {/* детали проблемы */}
                 <Route path="requests" element={<ManagerRequestsPage />} /> {/* список обращений */}
                 <Route path="requests/:requestId" element={<ManagerRequestDetailPage />} /> {/* детали обращения */}
                 <Route path="moderation" element={<ManagerModerationPage />} /> {/* модерация */}
                 <Route path="moderation/reviews/:reviewId" element={<ManagerReviewDetailPage />} /> {/* отзыв на товар */}
                 <Route path="moderation/feedback/:feedbackId" element={<ManagerFeedbackDetailPage />} /> {/* оценка заказа */}
               </Route>
          </Routes>
          {/* Один Drawer обслуживает все покупательские страницы. Если
              разместить его в каждой странице, при навигации терялся бы фокус
              и создавались бы дублирующиеся модальные слои. */}
          <MiniCartDrawer />
          </CustomerCartProvider>
        </AuthProvider>
      </BrowserRouter>
    </>
  );
};

export default App;
