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
        globIgnores: ["**/pages/catalog/*.svg"],
        maximumFileSizeToCacheInBytes: 5 * 1024 * 1024,
        // Без этого SW отдаёт index.html на офлайн-запрос к /api/, и axios
        // получает HTML вместо JSON вместо честной сетевой ошибки.
        navigateFallbackDenylist: [/^\/api\//, /^\/storage\//, /^\/sanctum\//],
        runtimeCaching: [
          {
            urlPattern: /^https?:\/\/.*\/api\//,
            handler: "NetworkFirst",
            options: {
              cacheName: "teaseat-api",
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
