# ТЗ для программиста 1С: интеграция с сайтом MAJOR

## 1. Цель интеграции

Нужно реализовать двусторонний обмен между 1С и сайтом MAJOR.

Что должно работать:

1. 1С обновляет цены товаров на сайте.
2. Сайт автоматически отправляет оформленные заказы в 1С.
3. 1С может забирать заказы с сайта, если нужен pull-сценарий.
4. 1С может обновлять статусы заказов на сайте.
5. При необходимости можно расширить интеграцию на другие сущности: остатки, бренды, прайс-профили, справочники, статусы, клиентов.

## 2. Что уже реализовано на стороне сайта

На сайте уже есть готовые API и логика:

- выгрузка каталога товаров;
- выгрузка категорий;
- пакетное обновление цен;
- выгрузка заказов;
- обновление статуса заказа;
- автоматическая отправка нового заказа в 1С после оформления на сайте.

Важно:

- синхронизация **цен** из 1С на сайт уже поддерживается;
- синхронизация **самих товаров из 1С на сайт** отдельным endpoint сейчас **не реализована**;
- если нужен именно push товаров из 1С на сайт, нужно отдельно добавить новый endpoint, например `POST /api/integrations/catalog/products/sync`.

То есть прямо сейчас доступны два основных сценария:

1. сайт отдает каталог в 1С;
2. 1С обновляет на сайте цены для уже существующих товаров.

## 3. Базовый адрес

Продакшен-сайт:

- `https://мажорпоставка.рф`

Технически безопасный URL для интеграции:

- `https://xn--80aaahs0ajsajkon.xn--p1ai`

Во всех HTTP-запросах лучше использовать именно punycode-адрес:

```text
https://xn--80aaahs0ajsajkon.xn--p1ai
```

## 4. Авторизация

Все запросы из 1С к сайту должны выполняться с интеграционным токеном.

Поддерживаются два варианта:

```http
X-Integration-Key: <ERP_TOKEN>
```

или

```http
Authorization: Bearer <ERP_TOKEN>
```

Если токен неверный или отсутствует, сайт вернет:

```http
401 Unauthorized
```

## 5. Какие endpoint уже есть на сайте

### Каталог

```http
GET /api/integrations/catalog/products
GET /api/integrations/catalog/categories
POST /api/integrations/catalog/prices/sync
```

### Заказы

```http
GET /api/integrations/orders
GET /api/integrations/orders/{orderNumber}
PATCH /api/integrations/orders/{orderNumber}
```

## 6. Идентификаторы

Для устойчивой интеграции использовать идентификаторы в таком приоритете:

1. `external_id`
2. `id`
3. `slug`
4. `source_sheet + source_row`

Рекомендуемый ключ для 1С:

```text
external_id
```

Для товаров он выглядит так:

```text
product-123
```

Для заказов использовать:

```text
order.number
```

Например:

```text
ORD-20260406-00015
```

## 7. Выгрузка каталога товаров в 1С

### Метод

```http
GET /api/integrations/catalog/products
```

### Назначение

Нужно использовать для:

- первичной синхронизации каталога;
- поиска соответствия между товарами сайта и товарами в 1С;
- регулярной дельта-синхронизации по `updated_since`.

### Параметры

- `per_page` optional, `1..200`, по умолчанию `100`
- `updated_since` optional, дата или datetime
- `category` optional, имя или slug категории
- `sheet` optional, имя листа прайса
- `q` optional, строка поиска

### Пример запроса

```http
GET /api/integrations/catalog/products?per_page=100&updated_since=2026-04-01T00:00:00+03:00
```

### Пример ответа

```json
{
  "data": [
    {
      "id": 123,
      "external_id": "product-123",
      "slug": "profil-m",
      "title": "Профиль M",
      "name": "Профиль M",
      "description": "Описание товара",
      "measurement_label": "шт",
      "measurement_value": "2,0",
      "source_sheet": "Major",
      "source_row": 15,
      "has_video": false,
      "video_label": null,
      "image_url": "https://xn--80aaahs0ajsajkon.xn--p1ai/storage/...",
      "product_url": "https://xn--80aaahs0ajsajkon.xn--p1ai/products/profil-m",
      "price_from": 530,
      "category": {
        "id": 5,
        "name": "Профиля",
        "slug": "profiliya",
        "parent": null
      },
      "prices": [
        {
          "column_index": 1,
          "label": "Цена 1",
          "display_value": "530,00",
          "min_amount": 530
        }
      ],
      "updated_at": "2026-04-06T14:21:00+03:00",
      "created_at": "2026-04-02T10:00:00+03:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 100,
    "total": 1221,
    "last_page": 13,
    "next_page_url": "https://xn--80aaahs0ajsajkon.xn--p1ai/api/integrations/catalog/products?page=2",
    "prev_page_url": null
  },
  "integration": {
    "service": "erp",
    "generated_at": "2026-04-06T14:25:00+03:00"
  }
}
```

## 8. Выгрузка категорий

### Метод

```http
GET /api/integrations/catalog/categories
```

### Назначение

Используется для:

- первичной загрузки структуры каталога;
- сопоставления разделов в 1С;
- построения витрин или справочников категорий на стороне 1С.

### Пример ответа

```json
{
  "data": [
    {
      "id": 1,
      "name": "Профиля",
      "slug": "profiliya",
      "accent_color": "#d11117",
      "product_count": 43,
      "children": [
        {
          "id": 10,
          "name": "Стеновые профили",
          "slug": "stenovye-profili",
          "accent_color": "#d11117",
          "product_count": 18
        }
      ]
    }
  ],
  "integration": {
    "service": "erp",
    "generated_at": "2026-04-06T14:25:00+03:00"
  }
}
```

## 9. Обновление цен из 1С на сайт

### Метод

```http
POST /api/integrations/catalog/prices/sync
```

### Назначение

Основной рабочий сценарий:

- 1С передает цены на сайт;
- сайт обновляет одну или несколько ценовых колонок товара;
- сайт пересчитывает `price_from`.

### Ограничения

- за один запрос: от `1` до `1000` товаров;
- товар должен быть найден по одному из идентификаторов.

### Структура запроса

```json
{
  "reset_missing": true,
  "items": [
    {
      "external_id": "product-123",
      "price_from": 1320,
      "prices": [
        {
          "column_index": 1,
          "label": "Цена 1",
          "display_value": "1490,00",
          "min_amount": 1490
        },
        {
          "column_index": 2,
          "label": "Цена дилера",
          "display_value": "1320,00",
          "min_amount": 1320
        }
      ]
    }
  ]
}
```

### Поля

- `reset_missing` optional boolean
- `items[].id` optional integer
- `items[].external_id` optional string
- `items[].slug` optional string
- `items[].source_sheet` optional string
- `items[].source_row` optional integer
- `items[].price_from` optional number
- `items[].prices` optional array
- `items[].prices[].column_index` required if передается `prices`
- `items[].prices[].label` optional
- `items[].prices[].display_value` optional
- `items[].prices[].min_amount` optional

### Логика

- если `price_from` не передан, сайт возьмет минимальную цену из `prices`;
- если `display_value` не передан, сайт сформирует его сам;
- если `reset_missing=true`, то старые цены, которых нет в payload, будут удалены у этого товара;
- если товар не найден, запись попадет в `errors`, а не сломает весь пакет.

### Ответ

```json
{
  "data": {
    "updated": 1,
    "skipped": 0,
    "errors": []
  },
  "integration": {
    "service": "erp",
    "updated_at": "2026-04-06T14:30:00+03:00"
  }
}
```

### Рекомендуемый регламент

- дельта-обновление каждые `5-15` минут;
- пакетами по `100-500` товаров;
- либо сразу по событию изменения цен в 1С.

## 10. Заказы: как сайт отправляет их в 1С

На стороне сайта уже реализован **push** нового заказа в 1С сразу после оформления.

Это значит:

- клиент оформляет заказ на сайте;
- сайт отправляет POST в 1С;
- 1С должна принять этот запрос и создать документ заказа.

### Что должен реализовать программист 1С

Нужно сделать HTTP endpoint на стороне 1С для приема заказов от сайта.

URL этого обработчика задается на сайте в:

```env
ERP_ORDERS_PUSH_URL
```

Авторизация входящего запроса от сайта к 1С:

```http
X-Integration-Key: <ERP_OUTGOING_TOKEN>
```

Заголовок можно поменять, но по умолчанию он именно такой.

### Пример payload, который сайт отправляет в 1С

```json
{
  "order": {
    "id": 77,
    "number": "ORD-20260406-00077",
    "status": "new",
    "payment_status": "pending",
    "payment_method": null,
    "payment_reference": null,
    "placed_at": "2026-04-06T14:33:00+03:00",
    "comment": "Позвонить перед доставкой",
    "price_profile_name": "Партнерский прайс",
    "items_count": 2,
    "subtotal_amount": 3180,
    "total_amount": 3180,
    "currency": "RUB"
  },
  "customer": {
    "id": 15,
    "name": "Иван Клиент",
    "company": "ООО Объект",
    "login": "partner15",
    "email": "partner15@example.com",
    "contact_person": "Алексей",
    "phone": "+7 999 111-22-33",
    "telegram": "@majorbuyer",
    "delivery_address": "Саратов, ул. Тестовая, 5",
    "price_profile": "Партнерский прайс"
  },
  "items": [
    {
      "id": 301,
      "product_id": 123,
      "external_id": "product-123",
      "product_title": "Профиль M",
      "product_slug": "profil-m",
      "quantity": 3,
      "price_label": "Цена 2",
      "unit_price": 530,
      "line_total": 1590,
      "source_sheet": "Major",
      "measurement_value": "2,0"
    }
  ]
}
```

### Как должна отвечать 1С

Если заказ успешно принят и создан:

```http
200 OK
```

```json
{
  "status": "accepted",
  "integration_reference": "1C-ORDER-000077"
}
```

### Важное требование

На стороне 1С должна быть **идемпотентность** по `order.number`.

Это значит:

- если сайт отправит один и тот же заказ повторно;
- 1С не должна создать дубль;
- 1С должна найти уже созданный документ и вернуть успешный ответ.

## 11. Выгрузка заказов из сайта в 1С по запросу

Если на стороне 1С удобнее не только принимать push, но и периодически забирать заказы, для этого уже есть API.

### Список заказов

```http
GET /api/integrations/orders
```

### Один заказ

```http
GET /api/integrations/orders/{orderNumber}
```

### Фильтры списка

- `per_page`
- `updated_since`
- `status`
- `payment_status`

### Пример ответа

```json
{
  "data": [
    {
      "id": 77,
      "number": "ORD-20260406-00077",
      "status": "new",
      "payment_status": "pending",
      "payment_method": null,
      "payment_reference": null,
      "integration_reference": "1C-ORDER-000077",
      "integration_status": "synced",
      "integration_last_error": null,
      "price_profile_name": "Партнерский прайс",
      "comment": "Позвонить перед доставкой",
      "manager_comment": "Связались, заказ в работе",
      "customer": {
        "id": 15,
        "name": "Иван Клиент",
        "company": "ООО Объект",
        "login": "partner15",
        "email": "partner15@example.com",
        "contact_person": "Алексей",
        "phone": "+7 999 111-22-33",
        "telegram": "@majorbuyer",
        "delivery_address": "Саратов, ул. Тестовая, 5"
      },
      "totals": {
        "items_count": 2,
        "subtotal_amount": 3180,
        "total_amount": 3180,
        "paid_amount": null,
        "currency": "RUB"
      },
      "placed_at": "2026-04-06T14:33:00+03:00",
      "paid_at": null,
      "integration_synced_at": "2026-04-06T14:34:00+03:00",
      "updated_at": "2026-04-06T14:35:00+03:00",
      "items": [
        {
          "id": 301,
          "product_id": 123,
          "product_title": "Профиль M",
          "product_slug": "profil-m",
          "product_url": "https://xn--80aaahs0ajsajkon.xn--p1ai/products/profil-m",
          "image_url": "https://xn--80aaahs0ajsajkon.xn--p1ai/storage/...",
          "quantity": 3,
          "price_label": "Цена 2",
          "unit_price": 530,
          "line_total": 1590,
          "source_sheet": "Major",
          "measurement_value": "2,0"
        }
      ]
    }
  ]
}
```

## 12. Обновление статуса заказа из 1С на сайт

### Метод

```http
PATCH /api/integrations/orders/{orderNumber}
```

### Назначение

1С может:

- подтвердить заказ;
- записать внутренний номер документа 1С;
- обновить статус оплаты;
- передать комментарий или отметку об обработке.

### Пример запроса

```json
{
  "status": "processing",
  "payment_status": "pending",
  "integration_reference": "1C-ORDER-000077",
  "integration_synced_at": "2026-04-06T14:35:00+03:00",
  "comment": "Заказ принят в 1С"
}
```

### Какие поля можно передавать

- `status`
- `payment_status`
- `payment_method`
- `payment_reference`
- `paid_amount`
- `paid_at`
- `integration_reference`
- `integration_synced_at`
- `payment_payload`
- `comment`

### Важная особенность

Если:

- передан `payment_status = paid`, но не передан `paid_at`,

то сайт сам поставит текущее время оплаты.

Если:

- передан `integration_reference`, но не передан `integration_synced_at`,

то сайт сам поставит текущее время синхронизации.

## 13. Статусы

### Статусы заказа

- `new`
- `processing`
- `completed`
- `canceled`
- `payment_failed`

### Статусы оплаты

- `pending`
- `paid`
- `failed`
- `canceled`

### Статусы интеграции

- `pending`
- `synced`
- `failed`

## 14. Ошибки и повторные попытки

### Сайт возвращает

- `200` — успешная операция
- `401` — неверный или отсутствующий токен
- `422` — невалидный payload

### Требования к 1С

На стороне 1С нужно обязательно сделать:

- логирование всех ошибок интеграции;
- повтор запроса при временной сетевой ошибке;
- защиту от дублей по `order.number`;
- пакетную обработку обновления цен;
- регламентное задание для обмена.

## 15. Что именно нужно сделать программисту 1С

### Обязательно

1. Реализовать HTTP-клиент к сайту для:
   - `GET /api/integrations/catalog/products`
   - `GET /api/integrations/catalog/categories`
   - `POST /api/integrations/catalog/prices/sync`
   - `GET /api/integrations/orders`
   - `GET /api/integrations/orders/{orderNumber}`
   - `PATCH /api/integrations/orders/{orderNumber}`
2. Реализовать HTTP endpoint на стороне 1С для приема заказов от сайта.
3. Сделать регламентные задания:
   - обновление цен;
   - опционально загрузка заказов;
   - повторная отправка при ошибках.

### Рекомендуется

1. Хранить сопоставление товаров сайта и товаров 1С по `external_id`.
2. Для заказов хранить соответствие по `order.number`.
3. Вести журнал интеграции с датой, payload, статусом и текстом ошибки.

## 16. Если понадобится расширение интеграции

Сейчас на сайте уже можно безопасно забирать почти любые данные, если добавить отдельные типизированные endpoint.

Примеры расширения:

- остатки: `GET /api/integrations/stocks`
- бренды: `GET /api/integrations/brands`
- прайс-профили: `GET /api/integrations/price-profiles`
- клиенты: `GET /api/integrations/customers`
- push товаров из 1С на сайт: `POST /api/integrations/catalog/products/sync`

Важно:

не нужно делать один “универсальный endpoint для всего подряд”.

Правильная схема:

- каждая сущность отдается отдельным endpoint;
- у каждой сущности есть свой контракт;
- у каждого обмена есть свой идентификатор и свой регламент.

## 17. Готовый короткий текст постановки для передачи 1С-разработчику

Ниже текст, который можно переслать как задачу без дополнительных пояснений:

---

Нужно реализовать интеграцию 1С с сайтом MAJOR.

Что требуется:

1. 1С должна забирать каталог товаров и категорий с сайта.
2. 1С должна отправлять на сайт обновления цен.
3. Сайт уже умеет автоматически отправлять новые заказы в 1С, поэтому на стороне 1С нужен HTTP endpoint для приема заказов.
4. 1С должна уметь обновлять статус заказа на сайте и записывать свой внутренний номер документа.
5. На стороне 1С обязательно сделать идемпотентность по номеру заказа сайта, чтобы не создавались дубли.

Использовать API сайта:

- `GET /api/integrations/catalog/products`
- `GET /api/integrations/catalog/categories`
- `POST /api/integrations/catalog/prices/sync`
- `GET /api/integrations/orders`
- `GET /api/integrations/orders/{orderNumber}`
- `PATCH /api/integrations/orders/{orderNumber}`

Авторизация:

- `X-Integration-Key: <ERP_TOKEN>`
или
- `Authorization: Bearer <ERP_TOKEN>`

Базовый адрес:

- `https://xn--80aaahs0ajsajkon.xn--p1ai`

Для товаров использовать идентификатор `external_id`, например `product-123`.
Для заказов использовать `order.number`, например `ORD-20260406-00077`.

Если понадобится двусторонняя синхронизация не только цен, но и самих товаров из 1С на сайт, это нужно будет делать отдельным новым endpoint, потому что сейчас на сайте реализована синхронизация именно цен и выгрузка каталога наружу.

---

## 18. Где лежит этот документ

Файл находится в проекте:

- `docs/1c-integration-task.md`

