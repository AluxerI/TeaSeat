import { defineConfig, devices } from "@playwright/test";

export default defineConfig({
  testDir: "./e2e",
  fullyParallel: true,
  retries: process.env.CI ? 2 : 0,
  reporter: "list",
  use: {
    baseURL: "http://127.0.0.1:4173",
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
  },
  projects: [
    {
      name: "mobile-chromium",
      use: { ...devices["Pixel 7"] },
    },
  ],
  webServer: {
    // Не используем общий `npm run dev -- --host`: в изолированном CI Vite
    // может не иметь права перечислять сетевые интерфейсы. Loopback достаточен.
    // Локальный бинарник не обращается к npm registry и одинаково работает
    // в offline CI и на машине разработчика после npm ci.
    command: "./node_modules/.bin/vite --host 127.0.0.1 --port 4173",
    url: "http://127.0.0.1:4173",
    reuseExistingServer: !process.env.CI,
  },
});
