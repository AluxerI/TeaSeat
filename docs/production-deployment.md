# Production deployment

`docker-compose.production.yml` запускает production-подобный стек:

- `gateway` — единственный опубликованный HTTP-порт;
- `frontend` — статическая Vite-сборка в Nginx;
- `backend` — Laravel Octane/RoadRunner;
- `worker` — очередь Laravel из того же backend-образа;
- `pgsql` и `redis` — только во внутренней Docker-сети.

Backend и worker используют один образ намеренно. Docker хранит его слои один
раз, поэтому второй контейнер не создаёт вторую копию приложения на диске.

## Локальная production-проверка

Создайте локальный файл окружения, который не попадёт в Git:

```bash
cp backend/.env.production.example backend/.env.production
```

Для локальной проверки задайте в `backend/.env.production`:

```dotenv
APP_KEY=base64:replace-with-generated-key
APP_URL=http://localhost:8080
FRONTEND_URL=http://localhost:8080
DB_DATABASE=teaseat
DB_USERNAME=teaseat
DB_PASSWORD=replace-with-random-password
REDIS_PASSWORD=replace-with-another-random-password
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
SANCTUM_STATEFUL_DOMAINS=localhost:8080,127.0.0.1:8080
```

Сгенерировать `APP_KEY` уже собранным образом:

```bash
docker run --rm --entrypoint php teaseat-backend:prod artisan key:generate --show
```

Проверить итоговую конфигурацию и собрать стек:

```bash
docker compose --env-file backend/.env.production -f docker-compose.production.yml config --quiet
docker compose --env-file backend/.env.production -f docker-compose.production.yml build
docker compose --env-file backend/.env.production -f docker-compose.production.yml up -d pgsql redis
docker compose --env-file backend/.env.production -f docker-compose.production.yml run --rm backend php artisan migrate --force
docker compose --env-file backend/.env.production -f docker-compose.production.yml up -d --wait
```

Проверки:

```bash
docker compose --env-file backend/.env.production -f docker-compose.production.yml ps
curl -I http://127.0.0.1:8080/
curl -I http://127.0.0.1:8080/up
```

Откройте `http://localhost:8080` и проверьте клиентскую часть, вход и
`http://localhost:8080/admin`.

Остановка без удаления данных:

```bash
docker compose --env-file backend/.env.production -f docker-compose.production.yml down
```

Не используйте `down -v`: этот флаг удалит БД, Redis, публичные изображения и
временные файлы импорта.

## Перед запуском на VPS

В серверном `backend/.env.production` замените локальные адреса реальным HTTPS-
доменом, включите `SESSION_SECURE_COOKIE=true`, укажите домен сессии и новые
случайные пароли. Сам файл не копируется в образ и не коммитится.

HTTP-конфигурация предназначена для локального smoke-теста и первичной проверки
сервера. Публикация в интернет выполняется только после добавления TLS,
ограничения firewall и автоматических резервных копий.
