# TeaSeat frontend

React 19 + TypeScript + Vite. Один frontend содержит клиентский магазин,
офлайн-PWA продавца (`/seller`) и интерфейс сборщика (`/picker`).

## Локальный запуск

Требуется Node.js 20.19+ и npm.

```bash
npm ci
npm run dev
```

Vite слушает `http://localhost:3000` и в Docker проксирует `/api`, `/sanctum`
и `/storage` в сервис `backend:8000`. Для запуска вне Docker задайте адрес API:

```bash
cp .env.example .env
```

## Проверки

```bash
npm run typecheck
npm test
npm run build
```

`package-lock.json` — единственный lock-файл проекта; Docker также использует
`npm ci`, поэтому зависимости локальной и контейнерной сборки совпадают.
