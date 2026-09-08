# Временный публичный доступ

Контур предназначен только для демонстрации. Он выдаёт случайный адрес
`https://....trycloudflare.com`; после пересоздания `cloudflared` адрес может
измениться.

## Перед первым запуском

В `backend/.env` должны быть собственный `APP_KEY` и актуальные параметры базы.
Ключ из старого `.env.example` был публичным и не должен использоваться:

```bash
docker compose exec backend php artisan key:generate --force
docker compose exec backend php artisan optimize:clear
docker compose restart backend worker
```

Смена `APP_KEY` завершит существующие сессии. Для проекта до выпуска это
ожидаемое поведение.

## Запуск

```bash
docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.public.yml up -d --build
docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.public.yml ps
docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.public.yml logs cloudflared
```

В логах `cloudflared` появится ссылка вида:

```text
https://random-words.trycloudflare.com
```

Проверить локальный gateway можно по `http://127.0.0.1:8080/gateway-health`.
Через публичный адрес должны открываться сайт, `/api`, `/storage` и `/admin`.

## Остановка публичного доступа

```bash
docker compose -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.public.yml stop cloudflared gateway
```

Остановка этих двух контейнеров не останавливает backend, worker, frontend,
PostgreSQL и Redis. Их порты привязаны только к `127.0.0.1` и не публикуются в
локальную сеть или интернет.
