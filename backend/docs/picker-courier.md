# API сборщика и курьера

Все маршруты требуют Sanctum-токен:

```http
Authorization: Bearer <token>
```

Сотрудник видит только активные точки из `user_warehouse`. Роли можно
совмещать. Сборщик работает только с онлайн-заказами; продажи продавца и
заказы поставщика в этот workflow не входят.

## Жизненный цикл

```text
confirmed
  -> processing
  -> packed (складская часть)
  -> ready_for_delivery (основной заказ или межскладская отправка)
  -> shipped
  -> delivered
```

`packed` означает, что складская часть собрана и уже находится в точке
консолидации. Это внутренний статус: он не означает доставку покупателю.
`awaiting_receipt` используется только для межскладской упаковки: курьер уже
привёз её в точку консолидации, но сборщик этой точки ещё не подтвердил приём.

Checkout резервирует товар. Физический `quantity` и
`reserved_online_quantity` уменьшаются одновременно, когда сборщик завершает
упаковку складской части. Курьер больше не выполняет складское списание.

## Сборщик

Роль: `picker`.

Права:

- `view picking orders`;
- `manage own picking orders`;
- `report picking shortage`.

### Очередь

```http
GET /api/picker/orders
```

Фильтры: `status`, `warehouse_id`, `job_type=source|consolidation`, `mine=1`,
`per_page`.

`source` означает упаковку товаров на складе-источнике. `consolidation`
означает объединение нескольких уже упакованных частей в точке доставки.

```http
GET  /api/picker/orders/{order}
POST /api/picker/orders/{order}/take
POST /api/picker/orders/{order}/release
POST /api/picker/orders/{order}/complete
```

Захват защищён `SELECT ... FOR UPDATE`: два сборщика не получат один заказ.
Отказ возможен только до завершения упаковки.

### Передача менеджеру

```http
POST /api/picker/orders/{order}/escalate
```

```json
{
  "comment": "Повреждена упаковка товара"
}
```

Заказ и его основной клиентский заказ переходят в `manager_review`.
Комментарий сохраняется в истории статусов.

### Недостача

```http
POST /api/picker/orders/{order}/shortage
```

```json
{
  "product_id": 15,
  "shortage_quantity": 2,
  "comment": "На полке меньше товара, чем в учёте"
}
```

Создаётся `fulfillment_issue` с причиной `physical_stock_discrepancy`. До
решения менеджера остаток не списывается.

### Приём межскладских упаковок

```http
GET  /api/picker/incoming-transfers
POST /api/picker/incoming-transfers/{order}/receive
```

Курьер только отмечает прибытие. Статус `packed` складская часть получает
после подтверждения сборщика точки назначения. `delivered` получает только
основной заказ после фактической доставки покупателю.

## Курьер

Роль `courier` обслуживает:

- клиентские доставки с типом `courier` или `express`;
- перемещения упакованных частей между рабочими точками.

```http
GET  /api/courier/deliveries
GET  /api/courier/deliveries/{order}
POST /api/courier/deliveries/{order}/claim
POST /api/courier/deliveries/{order}/release
POST /api/courier/deliveries/{order}/start
POST /api/courier/deliveries/{order}/deliver
```

Фильтры списка: `status`, `warehouse_id`,
`delivery_kind=transfer|customer`, `mine=1`, `per_page`.

До `start` курьер может отказаться. После начала доставки вмешивается
менеджер. Для клиентской доставки ответ содержит способ оплаты, итог заказа и
`amount_to_collect`. Для безналичной оплаты это `0`.

## Назначение менеджером

```http
POST /api/manager/deliveries/{order}/assign-courier
```

```json
{
  "courier_id": 42
}
```

Менеджер может назначить только активного курьера, прикреплённого к точке
отправления. Менеджер ограничен своими активными точками, администратор — нет.

## Возврат упакованного товара

```http
POST /api/manager/orders/{order}/return-to-stock
```

```json
{
  "reason": "Обнаружена ошибка упаковки"
}
```

Операция создаёт движение `online_return`, возвращает физический остаток и
одновременно восстанавливает онлайн-резерв. Автоматический возврат разрешён,
только пока упаковка остаётся на исходной точке. Уже перемещённый составной
заказ требует ручного решения менеджера.

## Способы доставки

`delivery_methods.type`:

- `courier` — обычный курьер TeaSeat;
- `express` — экспресс-курьер TeaSeat;
- `pickup` — самовывоз;
- `external` — внешняя служба.

Для `external` используется необязательный стабильный код, например
`provider_code = russian_post`.

Заказы `pickup` и `external` не попадают во внутреннюю очередь курьеров.
После упаковки менеджер проводит их дальше через обычное управление заказом:
для внешней службы — после фактической передачи подрядчику, для самовывоза —
после выдачи покупателю.

## Проверка

```bash
php artisan migrate
php artisan test --filter=PickingDeliveryWorkflowTest
php artisan test
```
