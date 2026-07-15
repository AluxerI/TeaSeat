# Контракт API подарков для frontend

Версия контракта: `1.0`

Дата фиксации: `2026-07-15`

Базовый URL: `/api`

Машиночитаемое описание: [`openapi/gifts-api.yaml`](openapi/gifts-api.yaml).
Подробная бизнес-логика: [`gifts-api.md`](gifts-api.md).

## Общие правила

Все маршруты требуют Sanctum-токен:

```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

Пользователь определяется только по токену. `user_id`, владелец подарка,
наценка, цена и доступность товара не принимаются от frontend как доверенные
данные.

Основные ответы:

- одиночный ресурс: `{"data": {...}}`;
- список: `{"data": [...]}` либо `{"data": [...], "meta": {...}}`;
- ошибка бизнес-правила: `{"message": "...", "code": "..."}`;
- ошибка Laravel-валидации: `{"message": "...", "errors": {...}}`.

## Рекомендуемый поток простого конструктора

1. Получить `GET /gift-constructor/simple/options`.
2. Показать только коробки из `data.boxes`.
3. Для выбранной коробки прочитать `simple_requirements`.
4. Собрать массивы `tea_product_size_ids` и `sweet_product_size_ids` точно
   указанной длины. Повторять один `product_size_id` разрешено.
5. Получить серверную цену через `POST /gift-constructor/simple/quote`.
6. Создать приватный шаблон через `POST /gift-constructor/simple/gifts`.
7. Сохранить возвращённые `id` и `version`.
8. Добавить экземпляр в корзину через `POST /cart/gifts` с новым
   `client_instance_id`.

Пример маленького набора:

```json
{
  "box_profile_id": 4,
  "name": "Чайный вечер",
  "tea_product_size_ids": [11, 11],
  "sweet_product_size_ids": [27]
}
```

Пример большого набора:

```json
{
  "box_profile_id": 5,
  "name": "Большой подарок",
  "tea_product_size_ids": [11, 11, 15, 19, 23],
  "sweet_product_size_ids": [27, 27]
}
```

Frontend не должен зашивать правила `2 + 1` или `5 + 2`: администратор может
изменить их в профиле коробки.

## Рекомендуемый поток сложного конструктора

1. Получить `GET /gift-constructor/advanced/options`.
2. Разместить элементы на сетке, используя размеры `product_size.size`.
3. Проверять черновую раскладку через
   `POST /gift-constructor/advanced/validate-layout`.
4. Получить цену через `POST /gift-constructor/advanced/quote`.
5. Создать подарок через `POST /gift-constructor/advanced/gifts`.
6. Добавить созданную версию в корзину.

Координаты начинаются с нуля. Backend повторно проверяет выход за границы,
пересечения, поворот, вес, активность товара и кратность складского количества.

## Матрица маршрутов

| Метод и путь | Имя маршрута | Назначение |
| --- | --- | --- |
| `GET /gift-constructor/simple/options` | `gift-constructor.simple.options` | Коробки и товары простого конструктора |
| `POST /gift-constructor/simple/quote` | `gift-constructor.simple.quote` | Цена без записи подарка |
| `POST /gift-constructor/simple/gifts` | `gift-constructor.simple.store` | Создать приватный подарок |
| `GET /gift-constructor/advanced/options` | `gift-constructor.advanced.options` | Полный каталог коробок и форматов |
| `POST /gift-constructor/advanced/validate-layout` | `gift-constructor.advanced.validate` | Проверить раскладку |
| `POST /gift-constructor/advanced/quote` | `gift-constructor.advanced.quote` | Рассчитать сложный подарок |
| `POST /gift-constructor/advanced/gifts` | `gift-constructor.advanced.store` | Создать сложный подарок |
| `GET /gifts` | `gifts.index` | Собственные активные подарки |
| `GET /gifts/{gift}` | `gifts.show` | Собственный подарок |
| `PUT /gifts/{gift}` | `gifts.update` | Полностью заменить раскладку; версия увеличится |
| `DELETE /gifts/{gift}` | `gifts.destroy` | Архивировать подарок |
| `POST /cart/gifts` | `cart.gifts.store` | Добавить версию подарка в корзину |
| `PUT /cart/gifts/{orderGift}` | `cart.gifts.update` | Изменить количество экземпляров |
| `DELETE /cart/gifts/{orderGift}` | `cart.gifts.destroy` | Удалить экземпляр из корзины |

Маршруты готовых магазинных наборов предназначены для сборщика:

| Метод и путь | Право | Назначение |
| --- | --- | --- |
| `GET /picker/assembled-gifts` | `view picking orders` | Каталог готовых подарочных SKU и остатки |
| `POST /picker/assembled-gifts/{product}/replenish` | `manage own picking orders` | Списать компоненты и оприходовать готовые наборы |

## Идентификаторы и версии

- `product_size_id` — конкретный товар, его количество и размер в сетке;
- `gift_id` — приватный редактируемый шаблон пользователя;
- `gift_version` — версия шаблона на момент добавления;
- `orderGift` — конкретный экземпляр подарка внутри корзины;
- `client_instance_id` — UUID, создаваемый frontend для идемпотентного
  добавления в корзину;
- `client_item_id` — UUID элемента сложной раскладки.

После изменения подарка старую версию нельзя молча добавить в корзину. При
`gift_cart_rejected` frontend обновляет подарок и просит пользователя повторить
действие. Уже добавленный в корзину подарок хранится снимком.

## Цена и остатки

Наценка всегда приходит из профиля коробки. Поле `markup_amount` клиента
игнорируется и не входит в контракт. Акции рассчитываются по компонентам,
одинаковые SKU суммируются. Quote не резервирует остаток; финальная цена и
резерв повторно рассчитываются при checkout.

## Ошибки

| Статус / code | Действие frontend |
| --- | --- |
| `401` | Очистить токен и запросить вход |
| `403` | Сотруднику обновить роли и рабочие точки |
| `404` | Подарок удалён либо принадлежит другому пользователю |
| `422 gift_configuration_invalid` | Показать ошибку состава или раскладки |
| `422 gift_cart_rejected` | Обновить подарок/корзину и показать конфликт |
| `422 gift_assembly_rejected` | Сборщику обновить остатки и состав партии |
| `422` с `errors` | Привязать сообщения к полям формы |

Frontend должен использовать поле `code` для выбора сценария, а `message` —
для отображения или журнала. Текст сообщения не является стабильным API.
