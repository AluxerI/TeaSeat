import { defineConfig } from "vitest/config";
import react from "@vitejs/plugin-react";

// Отдельный конфиг: в тестах не нужен vite-plugin-pwa (он пишет service worker
// и precache-манифест на диск, что в CI бессмысленно и медленно).
export default defineConfig({
  plugins: [react()],
  test: {
    environment: "jsdom",
    globals: true,
    setupFiles: ["./src/setupTests.ts"],
    css: false,
    include: ["src/**/*.{test,spec}.{ts,tsx}"],
    restoreMocks: true,
  },
});
