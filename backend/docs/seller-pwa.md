# PWA продавца: API и офлайн-синхронизация

## Граница ответственности

PWA хранит локальную очередь, но сервер остаётся источником истины для прав,
версий заказа, цен и складских операций. Все маршруты находятся в `backend`;
frontend не должен передавать `user_id` или `sales_channel`.

После `POST /api/auth/login` токен передаётся только заголовком:

```http
Authorization: Bearer <sanctum-token>
```

После регистрации устройства каждый запрос PWA также содержит:

```http
X-Device-UUID: 99f106ac-65ab-4f83-9c33-37b4bb17cf97
```

Backend определяет продавца по токену и разрешает продажи только в активных
точках из `user_warehouse`. Допустимы и `store`, и `warehouse`.

## Устройство

`POST /api/seller/devices/register`

```json
{
  "device_uuid": "99f106ac-65ab-4f83-9c33-37b4bb17cf97",
  "name": "Касса №1",
  "warehouse_id": 4
}
```

Один пользователь может иметь несколько устройств. Отозванное устройство не
может самостоятельно зарегистрироваться повторно с тем же UUID. Поля
`last_seen_at` и `last_sync_at` предназначены для контроля последней связи.

## Начальная синхронизация

`GET /api/seller/bootstrap?warehouse_id=4`

Ответ содержит:

- серверное время;
- выбранную рабочую точку;
- устройство;
- товары этой точки;
- физический остаток и оба резерва;
- подписанный снимок цены для каждого товара.

```json
{
  "data": {
    "server_time": "2026-07-14T18:00:00+03:00",
    "price_snapshot_ttl_hours": 48,
    "warehouse": {
      "id": 4,
      "name": "Основной склад",
      "type": "warehouse"
    },
    "products": [
      {
        "id": 18,
        "stock_unit": "gram",
        "sale_step": 10,
        "price_unit_quantity": 100,
        "pricing": {
          "unit_price": 250,
          "issued_at": "2026-07-14T18:00:00+03:00",
          "expires_at": "2026-07-16T18:00:00+03:00",
          "automatic_promotions": []
        },
        "pricing_token": "signed-token",
        "stock": {
          "quantity": 2000,
          "reserved_online_quantity": 300,
          "reserved_seller_quantity": 100,
          "available_quantity": 1600,
          "shortage_quantity": 0
        }
      }
    ]
  }
}
```

Снимок цены действует 48 часов по умолчанию. Важен момент физической продажи
`occurred_at`, а не момент поздней синхронизации. Продажа, созданная до
`expires_at`, принимается и после истечения снимка. После `expires_at` PWA не
должна создавать новые локальные продажи до обновления каталога. Backend также
отклоняет `occurred_at` из будущего с учётом допустимой погрешности часов.

Токен подписан ключом backend. PWA может показывать предварительную цену, но
при синхронизации backend сам пересчитывает строки из подписанных правил.
Изменённый токен отклоняется.

## CRUD заказов

### Создание

`POST /api/seller/orders`

```json
{
  "client_order_id": "43499891-e232-4e5b-a741-226125f759ab",
  "revision": 1,
  "warehouse_id": 4,
  "occurred_at": "2026-07-14T18:25:00+03:00",
  "payment_method": "card",
  "items": [
    {
      "product_id": 18,
      "quantity": 110,
      "pricing_token": "signed-token-from-bootstrap"
    }
  ]
}
```

Разрешены `cash` и `card`. Смешанная оплата, долг, физический покупатель и
фискальный номер не входят в этот этап. `orders.user_id` равен продавцу.

### Редактирование

`PUT /api/seller/orders/{order}` принимает полный снимок с `revision = 2`,
затем `3` и так далее. Клиент увеличивает версию ровно на единицу.

- повтор той же версии с тем же содержимым возвращает `duplicate = true`;
- та же версия с другим содержимым отклоняется;
- пропуск версии отклоняется;
- точку и `occurred_at` синхронизированного заказа менять нельзя;
- после `manager_review`, `completed` или `cancelled` редактирование запрещено.

Редактирование пересчитывает `reserved_seller_quantity` на разницу между
старой и новой полной версией. Поле `was_edited` помогает выявлять заказы,
которые исправлялись после первой синхронизации.

### Список и просмотр

```text
GET /api/seller/orders
GET /api/seller/orders/{order}
```

Список принимает фильтры `status`, `warehouse_id`, `date_from`, `date_to` и
`per_page`. Продавец получает только собственные заказы канала `seller`.

### Отмена

`POST /api/seller/orders/{order}/cancel`

```json
{
  "revision": 3
}
```

Отмена разрешена в `pending` и `seller_review` и освобождает резерв продавца.
Проведённую продажу нельзя отменить этим маршрутом.

## Завершение дня

`POST /api/seller/orders/complete` принимает до 100 заказов. Каждый обрабатывается
независимо, поэтому одна ошибка не откатывает успешные заказы.

```json
{
  "orders": [
    {"order_id": 150, "revision": 3},
    {"order_id": 151, "revision": 1}
  ]
}
```

Без конфликта backend:

1. уменьшает физический `quantity`;
2. снимает `reserved_seller_quantity`;
3. записывает `seller_sale` в `inventory_movements`;
4. переводит заказ в `completed`.

Операция идемпотентна: повтор не списывает товар второй раз.

При дефиците команда завершения не проводит остаток. Заказ получает
`seller_review`, резерв сохраняется, а PWA показывает товары для проверки.
`fulfillment_issues` на этом этапе ещё не создаются.

## Передача менеджеру

После ручной проверки продавец вызывает:

`POST /api/seller/orders/{order}/escalate`

```json
{
  "revision": 3
}
```

Backend повторно проверяет остатки. Если конфликт исчез, заказ завершается
обычно. Если проблема осталась, физическая продажа проводится, отрицательный
остаток не допускается, создаются минимальные `fulfillment_issues`, а заказ
получает `manager_review`.

Одна проблема хранит только:

```text
source_order_id
product_id
warehouse_id
reason
shortage_quantity
reserved_online_before
reserved_seller_before
status
```

Причины:

- `online_reservation_conflict`;
- `physical_stock_discrepancy`.

Статусы рассмотрения менеджером:

- `waiting` — ожидает;
- `in_review` — рассматривается;
- `closed` — дело закрыто, без утверждения, что дефицит обязательно устранён.

Пострадавший интернет-заказ заранее не выбирается. Будущий менеджерский API
будет динамически возвращать актуальные заказы-кандидаты из онлайн-резервов.
Окончательный выбор остаётся за менеджером.

## Пакетная офлайн-синхронизация

`POST /api/seller/sync`

```json
{
  "events": [
    {
      "event_id": "0b07b14e-45cf-44a1-a914-cd8498a2d983",
      "action": "upsert",
      "client_order_id": "43499891-e232-4e5b-a741-226125f759ab",
      "revision": 1,
      "warehouse_id": 4,
      "occurred_at": "2026-07-14T18:25:00+03:00",
      "payment_method": "cash",
      "items": [
        {
          "product_id": 18,
          "quantity": 110,
          "pricing_token": "signed-token-from-bootstrap"
        }
      ]
    },
    {
      "event_id": "d572e199-ee75-4eb5-a2a2-98570ff25271",
      "action": "complete",
      "order_id": 150,
      "revision": 1
    }
  ]
}
```

Действия: `upsert`, `cancel`, `complete`, `escalate`. `event_id` нужен PWA для
сопоставления ответа с локальной очередью. Идемпотентность изменения заказа
обеспечивают `client_order_id`, `revision` и хеш полного содержимого; складские
операции дополнительно защищены уникальными ключами `inventory_movements`.

Каждый результат имеет собственный `accepted`, `duplicate`, `completed`,
`seller_review`, `manager_review`, `cancelled` или `rejected`.

Если при закрытии магазина нет интернета, PWA хранит команды локально. Смена
остаётся в состоянии «требует синхронизации», пока сервер не вернёт результат.
Проблемные заказы автоматически менеджеру не передаются.

## Проверка backend

Так как добавлена новая миграция, достаточно обычного запуска:

```bash
php artisan migrate
php artisan test --filter=SellerPwaTest
php artisan test
```
