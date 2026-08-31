import React from 'react';
import ReactDOM from 'react-dom/client';
import './index.css';
import App from './App';

// Удаляем общий API-кэш, созданный ранними версиями PWA. В нём могли остаться
// авторизованные ответы seller/picker, не разделённые по пользователям.
if ("caches" in window) {
  void window.caches.delete("teaseat-api");
}

const root = ReactDOM.createRoot(
  document.getElementById('root') as HTMLElement
);
root.render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
);
