# Единый контракт API для приложений сотрудников

Версия контракта: `1.0`

Дата фиксации: `2026-07-14`

Базовый URL: `/api`

Этот документ — точка входа для frontend-разработчика PWA продавца,
сборщика, менеджера и курьера. Машиночитаемая версия находится в
[`openapi/staff-api.yaml`](openapi/staff-api.yaml). Подробности отдельных
процессов остаются в документах:

- [`staff-access.md`](staff-access.md) — роли и рабочие точки;
- [`seller-pwa.md`](seller-pwa.md) — офлайн-синхронизация продавца;
- [`manager-fulfillment.md`](manager-fulfillment.md) — дела менеджера;
- [`picker-courier.md`](picker-courier.md) — сборка и доставка.

## Общие правила

### Аутентификация и источник личности

```http
Authorization: Bearer <sanctum-token>
Accept: application/json
Content-Type: application/json
```

Токен выдаёт `POST /api/auth/login`. Backend определяет пользователя, роли и
рабочие точки только по токену. Поля `user_id`, `seller_id`, `picker_id`,
`manager_id`, `courier_id`, `sales_channel` и роли нельзя принимать от клиента
как источник полномочий.

После регистрации устройства все seller-запросы, кроме самой регистрации,
дополнительно требуют:

```http
X-Device-UUID: 99f106ac-65ab-4f83-9c33-37b4bb17cf97
```

Регистр и формат UUID нормализует backend. Устройство должно принадлежать
текущему продавцу и не быть отозвано.

### Контекст сотрудника

Вход:

```http
POST /api/auth/login

{
  "email": "staff@teaseat.test",
  "password": "password"
}
```

Успешный ответ `200`:

```json
{
  "message": "Login successful",
  "user": {
    "id": 12,
    "roles": ["seller", "picker"],
    "work_locations": [
      {
        "id": 4,
        "name": "Основной склад",
        "city": "Тула",
        "type": "warehouse",
        "is_online_fulfillment_enabled": true,
        "is_delivery_hub": true
      }
    ],
    "capabilities": {
      "admin": false,
      "manager": false,
      "seller": true,
      "picker": true,
      "courier": false,
      "can_access_filament": false
    }
  },
  "token": "1|...",
  "token_type": "Bearer"
}
```

Актуальный контекст можно обновить через `GET /api/user`. В этом ответе профиль
находится в поле `data`. Frontend строит разделы интерфейса по `capabilities`,
но это только UI: backend повторно проверяет право на каждом маршруте.

### Формат ответов

- одиночное чтение: `{"data": {...}}`;
- обычная команда: `{"message": "...", "data": {...}}`;
- список: `{"data": [...], "meta": {...}}`;
- пакет продавца: `{"results": [...]}` или
  `{"server_time": "...", "results": [...]}`;
- ошибка: `{"message": "...", "code": "..."}`; поле `code` есть у
  доменных конфликтов и специализированных запретов;
- ошибка валидации Laravel: `{"message": "...", "errors": {"field": [...]}}`.

Для списков `meta` содержит `current_page`, `last_page`, `per_page`, `total`.
Frontend не должен вычислять доступность команды только по статусу: ресурс
возвращает объект `actions` (`can_take`, `can_complete` и так далее).

### HTTP-статусы

| Статус | Значение для клиента |
| --- | --- |
| `200` | чтение, повтор идемпотентной команды или успешная команда |
| `201` | создан новый заказ продавца или зарегистрировано устройство |
| `401` | токен отсутствует, истёк или логин неверен |
| `403` | middleware не разрешает право либо специализированный staff API не даёт доступ к точке |
| `404` | объект не существует либо недоступен текущему сотруднику |
| `409` | объект существует, но переход состояния сейчас запрещён |
| `422` | тело/query не прошли валидацию; seller API также так отклоняет устройство или выбранную точку |
| `429` | превышен лимит запросов |

При `401` PWA очищает токен и просит войти снова. При `403` обновляет
`GET /api/user`; при `409` обновляет сам объект и показывает сообщение сервера;
при `422` связывает `errors` с полями формы.

## Матрица маршрутов

В колонке «Право» указано backend permission. Роль получает его через
`RolePermissionSeeder`; совмещение ролей разрешено.

### Общие

| Метод и путь | Имя маршрута | Доступ | Назначение |
| --- | --- | --- | --- |
| `POST /auth/login` | — | публичный | Получить Sanctum-токен и контекст |
| `GET /user` | `user.show` | любой токен | Обновить роли, права и активные точки |
| `POST /auth/logout` | — | любой токен | Удалить текущий токен |

### Продавец

Все маршруты после регистрации требуют `X-Device-UUID`.

| Метод и путь | Имя маршрута | Право | Тело / фильтры |
| --- | --- | --- | --- |
| `POST /seller/devices/register` | `seller.devices.register` | `create seller orders` | `device_uuid`, `name`, необязательный `warehouse_id` |
| `GET /seller/bootstrap` | `seller.bootstrap` | `create seller orders` | обязательный `warehouse_id` |
| `GET /seller/orders` | `seller.orders.index` | `view own seller orders` | `status`, `warehouse_id`, `date_from`, `date_to`, `per_page` |
| `GET /seller/orders/{order}` | `seller.orders.show` | `view own seller orders` | — |
| `POST /seller/orders` | `seller.orders.store` | `create seller orders` | полный снимок заказа |
| `PUT /seller/orders/{order}` | `seller.orders.update` | `create seller orders` | следующий полный снимок заказа |
| `POST /seller/orders/{order}/cancel` | `seller.orders.cancel` | `create seller orders` | `revision` |
| `POST /seller/orders/complete` | `seller.orders.complete` | `complete own seller orders` | массив `orders`, максимум 100 |
| `POST /seller/orders/{order}/escalate` | `seller.orders.escalate` | `complete own seller orders` | `revision` |
| `POST /seller/sync` | `seller.sync` | создание + завершение | массив `events`, максимум 100 |

Полный снимок заказа:

```json
{
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
}
```

`client_order_id` создаёт PWA один раз. `revision` начинается с `1` и
увеличивается ровно на единицу. `quantity` всегда целое число складских единиц:
штук либо граммов, кратных `sale_step`. Цена приходит из подписанного
`pricing_token`, а не из поля цены клиента.

Пакетная синхронизация принимает действия `upsert`, `cancel`, `complete`,
`escalate`. Каждое событие имеет уникальный локальный `event_id`; ошибка одного
события не откатывает остальные. PWA удаляет событие из очереди только после
получения результата для его `event_id`.

### Сборщик

| Метод и путь | Имя маршрута | Право | Тело / фильтры |
| --- | --- | --- | --- |
| `GET /picker/orders` | `picker.orders.index` | `view picking orders` | `status`, `warehouse_id`, `job_type`, `mine`, `per_page` |
| `GET /picker/orders/{order}` | `picker.orders.show` | `view picking orders` | — |
| `POST /picker/orders/{order}/take` | `picker.orders.take` | `manage own picking orders` | пустое тело |
| `POST /picker/orders/{order}/release` | `picker.orders.release` | `manage own picking orders` | пустое тело |
| `POST /picker/orders/{order}/complete` | `picker.orders.complete` | `manage own picking orders` | пустое тело |
| `POST /picker/orders/{order}/escalate` | `picker.orders.escalate` | `manage own picking orders` | `comment` (обязательно, до 1000) |
| `POST /picker/orders/{order}/shortage` | `picker.orders.shortage` | `report picking shortage` | `product_id`, `shortage_quantity`, `comment?` |
| `GET /picker/incoming-transfers` | `picker.transfers.index` | `view picking orders` | `per_page` |
| `POST /picker/incoming-transfers/{order}/receive` | `picker.transfers.receive` | `manage own picking orders` | пустое тело |

`job_type=source` — упаковка на складе-источнике;
`job_type=consolidation` — объединение частей в конечной точке. Захват заказа
атомарный. При `409 picking_transition_rejected` клиент обновляет заказ: его мог
взять или изменить другой сотрудник.

### Курьер

| Метод и путь | Имя маршрута | Право | Тело / фильтры |
| --- | --- | --- | --- |
| `GET /courier/deliveries` | `courier.deliveries.index` | `view assigned deliveries` | `status`, `warehouse_id`, `delivery_kind`, `mine`, `per_page` |
| `GET /courier/deliveries/{order}` | `courier.deliveries.show` | `view assigned deliveries` | — |
| `POST /courier/deliveries/{order}/claim` | `courier.deliveries.claim` | `update assigned deliveries` | пустое тело |
| `POST /courier/deliveries/{order}/release` | `courier.deliveries.release` | `update assigned deliveries` | пустое тело |
| `POST /courier/deliveries/{order}/start` | `courier.deliveries.start` | `update assigned deliveries` | пустое тело |
| `POST /courier/deliveries/{order}/deliver` | `courier.deliveries.deliver` | `update assigned deliveries` | пустое тело |

`delivery_kind=customer` — доставка покупателю; `transfer` — межскладское
перемещение. Ресурс клиентской доставки содержит контакт, адрес,
`payment.method`, `payment.order_total` и `payment.amount_to_collect`.

### Менеджер: проблемы, упаковки и назначения

Эти маршруты ограничены активными точками менеджера. Администратор видит все
точки.

| Метод и путь | Имя маршрута | Право | Тело / фильтры |
| --- | --- | --- | --- |
| `GET /manager/fulfillment-issues` | `manager.fulfillment-issues.index` | `view fulfillment issues` | `status`, `warehouse_id`, `product_id`, `reason`, `mine`, `per_page` |
| `GET /manager/fulfillment-issues/{issue}` | `manager.fulfillment-issues.show` | `view fulfillment issues` | — |
| `GET /manager/fulfillment-issues/{issue}/affected-orders` | `manager.fulfillment-issues.affected-orders` | `view fulfillment issues` | `per_page` |
| `POST /manager/fulfillment-issues/{issue}/take` | `manager.fulfillment-issues.take` | `manage fulfillment issues` | пустое тело |
| `POST /manager/fulfillment-issues/{issue}/release` | `manager.fulfillment-issues.release` | `manage fulfillment issues` | пустое тело |
| `POST /manager/fulfillment-issues/{issue}/close` | `manager.fulfillment-issues.close` | `manage fulfillment issues` | пустое тело |
| `POST /manager/deliveries/{order}/assign-courier` | `manager.deliveries.assign-courier` | `assign couriers` | `courier_id` |
| `POST /manager/orders/{order}/return-to-stock` | `manager.orders.return-to-stock` | `manage orders` | `reason` (обязательно, до 1000) |

Статусы дела: `waiting`, `in_review`, `closed`. `closed` означает завершение
текущего рассмотрения, но не утверждает, что причина устранена. Список
`affected-orders` динамический: backend не назначает пострадавший заказ и не
изменяет его.

### Менеджер и администратор: общее управление заказами

Префикс исторически называется `/admin`, но доступ определяется правом
`manage orders`, которое есть у `manager` и `admin`. В отличие от
специализированного `/manager` API, этот список сейчас глобальный и не
ограничивается назначенными точками.

| Метод и путь | Имя маршрута | Тело / фильтры |
| --- | --- | --- |
| `GET /admin/orders` | `management.orders.index` | `status`, `order_type`, `date_from`, `date_to`, `search`, `per_page` |
| `GET /admin/orders/stats` | `management.orders.stats` | `date_from`, `date_to` |
| `GET /admin/orders/{order}` | `management.orders.show` | — |
| `PUT /admin/orders/{order}/status` | `management.orders.update-status` | `status`, `internal_notes?` |
| `PUT /admin/orders/{order}/tracking` | `management.orders.update-tracking` | `tracking_number`, `carrier?` |
| `PUT /admin/orders/{order}/internal-notes` | `management.orders.update-internal-notes` | `internal_notes` |
| `PUT /admin/orders/{order}/cancel` | `management.orders.cancel` | `reason?` |
| `PUT /admin/orders/{order}/confirm` | `management.orders.confirm` | пустое тело |
| `PUT /admin/orders/{order}/ship` | `management.orders.ship` | пустое тело |
| `PUT /admin/orders/{order}/deliver` | `management.orders.deliver` | пустое тело |
| `PUT /admin/orders/{order}/delivery-method` | `management.orders.update-delivery-method` | `delivery_method_id`, `shipping_cost?` |

Недопустимый переход возвращает `409` и
`code=order_transition_rejected`. Статус онлайн-заказа нельзя перескочить через
workflow сборщика или курьера. Продажи канала `seller` меняются только seller
workflow и процессом fulfillment issues.

## Жизненные циклы

### Онлайн-заказ

| Этап | Кто выполняет | Следующий статус |
| --- | --- | --- |
| Checkout создал резерв | клиент/backend | `pending` |
| Менеджер подтвердил | manager/admin | `confirmed` |
| Сборщик взял | picker | `processing` |
| Сборщик упаковал и списал резерв | picker | `ready_for_delivery` |
| Курьер начал клиентскую доставку | courier | `shipped` |
| Курьер доставил | courier | `delivered` |

Для межскладской части после `shipped` курьер ставит `awaiting_receipt`, затем
сборщик точки назначения подтверждает приём. `pickup` и `external` не попадают
во внутреннюю очередь курьера и проводятся менеджером после реальной выдачи или
передачи подрядчику.

### Офлайн-продажа продавца

| Состояние | Смысл |
| --- | --- |
| `pending` | заказ синхронизирован, резерв продавца удерживается |
| `seller_review` | при завершении обнаружен конфликт; продавец должен проверить |
| `manager_review` | продавец подтвердил физическую продажу и передал дефицит менеджеру |
| `completed` | физический остаток списан один раз |
| `cancelled` | заказ отменён до проведения, резерв освобождён |

### Дело менеджера

| Команда | Переход |
| --- | --- |
| `take` | `waiting -> in_review` |
| `release` | `in_review -> waiting` |
| `close` | `in_review -> closed` |

## Идемпотентность и конкурентность

- заказ продавца: `client_order_id + revision + hash`;
- пакет продавца: `event_id` сопоставляет команду и ответ, а повтор защищают
  версия и уникальные складские движения;
- завершение продавца и складское списание сборщика не списывают остаток дважды;
- захват заказа сборщиком, доставки курьером и дела менеджером выполняется с
  блокировкой строки; проигравший конкурент получает `409`;
- повтор уже выполненного безопасного перехода может вернуть `200`, но frontend
  всегда принимает состояние из ответа сервера.

## Минимальный порядок интеграции frontend

1. Реализовать общий HTTP-клиент: Bearer, JSON, обработка `401/403/409/422/429`.
2. После входа сохранить токен, роли, capabilities и рабочие точки.
3. Для seller PWA зарегистрировать устройство, выбрать активную точку и
   выполнить bootstrap.
4. Хранить seller-события локально до результата синхронизации по `event_id`.
5. Для picker, courier и manager строить кнопки из `actions`, после каждой
   команды заменять локальный объект содержимым `data`.
6. Не оптимистично подтверждать складское списание, доставку или закрытие дела:
   финальное состояние всегда приходит от backend.
7. При изменении этого контракта сначала обновить именованные маршруты,
   OpenAPI и `StaffApiContractTest`, затем передавать новую версию frontend.
