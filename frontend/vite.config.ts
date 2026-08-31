import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import { VitePWA } from "vite-plugin-pwa";

export default defineConfig({
  plugins: [
    react(),
    VitePWA({
      registerType: "autoUpdate",
      manifest: {
        name: "Чайные посиделки",
        short_name: "Чайные посиделки",
        description:
          "Магазин чая и сладостей, конструктор подарков и рабочее место продавца",
        lang: "ru",
        start_url: "/",
        scope: "/",
        display: "standalone",
        orientation: "any",
        background_color: "#FFFFFF",
        theme_color: "#87A617",
        icons: [
          {
            src: "/header/logo.svg",
            sizes: "any",
            type: "image/svg+xml",
            purpose: "any",
          },
        ],
        shortcuts: [
          { name: "Новая продажа", url: "/seller/order/new" },
          { name: "Заказы продавца", url: "/seller/orders" },
        ],
      },
      workbox: {
        globPatterns: ["**/*.{js,css,html,ico,png,svg}"],
        // Тяжёлые иллюстрации каталога и 3D-конструктор не нужны для кассы при
        // первой установке. Они попадут в runtime cache после первого открытия.
        globIgnores: [
          "**/pages/catalog/*.svg",
          "**/pages/catalog/**/*.svg",
          "**/assets/ConstructorPage-*.js",
          "**/assets/ConstructorPage-*.css",
        ],
        maximumFileSizeToCacheInBytes: 5 * 1024 * 1024,
        // Без этого SW отдаёт index.html на офлайн-запрос к /api/, и axios
        // получает HTML вместо JSON вместо честной сетевой ошибки.
        navigateFallbackDenylist: [/^\/api\//, /^\/storage\//, /^\/sanctum\//],
        runtimeCaching: [
          {
            // Авторизованные ответы seller/picker нельзя складывать в общий
            // Cache Storage: кассовым устройством пользуются разные сотрудники.
            // Офлайн-данные seller уже хранятся отдельно в IndexedDB.
            urlPattern: /^https?:\/\/[^/]+\/api\/catalog(?:\?.*)?$/,
            handler: "NetworkFirst",
            options: {
              cacheName: "teaseat-public-catalog",
              expiration: {
                maxEntries: 100,
                maxAgeSeconds: 60 * 60 * 24,
              },
            },
          },
          {
            urlPattern: /^https?:\/\/.*\/storage\//,
            handler: "CacheFirst",
            options: {
              cacheName: "teaseat-images",
              expiration: {
                maxEntries: 200,
                maxAgeSeconds: 60 * 60 * 24 * 30,
              },
            },
          },
          {
            urlPattern: /^https?:\/\/[^/]+\/pages\/catalog\/.*\.svg$/,
            handler: "CacheFirst",
            options: {
              cacheName: "teaseat-catalog-art",
              expiration: {
                maxEntries: 30,
                maxAgeSeconds: 60 * 60 * 24 * 30,
              },
            },
          },
          {
            urlPattern:
              /^https?:\/\/[^/]+\/assets\/ConstructorPage-.*\.(?:js|css)$/,
            handler: "CacheFirst",
            options: {
              cacheName: "teaseat-constructor",
              expiration: {
                maxEntries: 4,
                maxAgeSeconds: 60 * 60 * 24 * 30,
              },
            },
          },
        ],
      },
    }),
  ],
  server: {
    port: 3000,
    proxy: {
      "/api": {
        target: "http://backend:8000",
        changeOrigin: true,
      },
      "/sanctum": {
        target: "http://backend:8000",
        changeOrigin: true,
      },
      "/storage": {
        target: "http://backend:8000",
        changeOrigin: true,
      },
    },
  },
});
