# Order System — мини-приложение на паттернах ООП

Учебный проект: система заказов на микросервисной архитектуре, где
каждый слой демонстрирует конкретный паттерн проектирования вживую,
а не как изолированный пример.

## Архитектура

```
                     ┌─────────────┐
                     │  React UI   │  (Vite dev-server)
                     └──────┬──────┘
                            │ HTTP
                     ┌──────▼──────┐
                     │    Nginx    │  API Gateway
                     │  (gateway)  │
                     └──┬───────┬──┘
            /api/orders │       │ /api/notifications
                 ┌──────▼───┐ ┌─▼─────────────────┐
                 │   PHP    │ │        Go          │
                 │  Orders  │ │   Notification     │
                 │ Service  │ │     Service         │
                 └────┬─────┘ └────────┬────────────┘
                      │ PDO            │ pq driver
                      └───────┬────────┘
                       ┌──────▼──────┐
                       │ PostgreSQL  │
                       │  orders_db  │
                       └─────────────┘
```

PHP и Go — полностью независимые микросервисы. Они не вызывают друг
друга напрямую через бизнес-методы; единственная связь — событие
`order.created`, которое PHP отправляет в Go по HTTP webhook
(см. раздел Observer ниже). У каждого сервиса свои таблицы в общей
базе: `orders`/`order_items` принадлежат PHP, `notification_logs` —
Go. Ни один сервис не лезет в таблицы другого.

## Какой паттерн — где и почему

| Паттерн | Где | Зачем здесь |
|---|---|---|
| **Repository** | `php-orders-service/src/Domain/Repository/OrderRepositoryInterface.php` + `Infrastructure/Persistence/PostgresOrderRepository.php` | Бизнес-логика обращается к данным через интерфейс, не зная, что это Postgres. Можно подставить in-memory реализацию для тестов. |
| **Strategy** | `php-orders-service/src/Domain/Strategy/*DiscountStrategy.php` | Каждый тип клиента (`regular`/`vip`/`wholesale`) считает скидку по-своему. Новый тип клиента = новый класс, без правки существующих. |
| **Factory Method** | `php-orders-service/src/Application/Factory/DiscountStrategyFactory.php` | Скрывает от `OrderService`, какой конкретный класс стратегии создаётся под конкретный tier. |
| **Observer** | `php-orders-service/src/Infrastructure/EventDispatcher/EventDispatcher.php` + `Infrastructure/Http/NotificationServiceListener.php` | `OrderService` публикует факт "заказ создан" и не знает, кто на это подписан. Сегодня подписчик — HTTP-вызов в Go; завтра можно добавить ещё слушателей без изменения `OrderService`. |
| **Dependency Injection** | `php-orders-service/public/index.php`, `go-notification-service/cmd/server/main.go` | Все зависимости (PDO, Repository, Factory, Dispatcher) создаются в одном месте и передаются через конструкторы. Ни один класс не делает `new` своих зависимостей сам. |
| **Strategy (Go)** | `go-notification-service/internal/strategy/channel.go` | Тот же принцип на стороне Go: канал доставки уведомления (`log`, `email`) — интерфейс `Channel`. Добавление Slack/SMS-канала не трогает `NotificationService`. |
| **Repository (Go)** | `go-notification-service/internal/repository/notification_repository.go` | Лог нотификаций хранится за интерфейсом `NotificationRepository`, как и в PHP-сервисе. |

## Поток данных при создании заказа

1. React отправляет `POST /api/orders` через Gateway.
2. Nginx проксирует запрос в PHP Orders Service.
3. `OrderController` парсит JSON в `CreateOrderRequest` (DTO).
4. `OrderService`:
   - создаёт доменный `Order` и `OrderItem`;
   - просит `DiscountStrategyFactory` дать стратегию под `customer_tier`;
   - считает скидку через эту стратегию;
   - сохраняет заказ через `OrderRepositoryInterface`;
   - публикует `OrderCreatedEvent` через `EventDispatcher`.
5. `EventDispatcher` вызывает подписанный `NotificationServiceListener`,
   который шлёт HTTP POST в Go-сервис: `/api/notifications/events`.
6. Go `NotificationHandler` принимает событие, передаёт в
   `NotificationService`, который прогоняет его через все `Channel`
   (сейчас — лог и "email") и сохраняет запись в `notification_logs`.
7. React дополнительно опрашивает `/api/notifications/logs`, чтобы
   показать, что событие реально долетело до Go-сервиса.

Если Go-сервис недоступен — HTTP-вызов уйдёт в таймаут за 1–2 секунды
и будет залогирован, но **не сломает создание заказа**: нотификация
— это побочный эффект, а не часть транзакции заказа.

## Запуск

```bash
docker compose up --build
```

После старта:

- **http://localhost** — фронтенд через Gateway (рекомендуемый способ открыть приложение)
- **http://localhost:8000/health** — PHP-сервис напрямую
- **http://localhost:8080/health** — Go-сервис напрямую
- **localhost:5432** — Postgres (`orders_user` / `orders_password` / `orders_db`)

Таблицы создаются автоматически при первом старте контейнера Postgres
из `postgres-init/001_init.sql`.

## API

### PHP Orders Service (`/api/orders`)

| Метод | Путь | Описание |
|---|---|---|
| POST | `/api/orders` | Создать заказ |
| GET | `/api/orders` | Список всех заказов (`?customer_id=` для фильтра) |
| GET | `/api/orders/{id}` | Получить заказ по ID |
| POST | `/api/orders/{id}/confirm` | Подтвердить заказ |
| POST | `/api/orders/{id}/cancel` | Отменить заказ |

Пример создания заказа:

```bash
curl -X POST http://localhost/api/orders \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": 42,
    "customer_tier": "vip",
    "items": [
      {"product_name": "Mechanical Keyboard", "quantity": 1, "unit_price": 120.00},
      {"product_name": "Mouse", "quantity": 2, "unit_price": 35.00}
    ]
  }'
```

### Go Notification Service (`/api/notifications`)

| Метод | Путь | Описание |
|---|---|---|
| POST | `/api/notifications/events` | Принять событие (вызывается PHP-сервисом, не фронтендом) |
| GET | `/api/notifications/logs` | Последние 100 обработанных нотификаций |

## Тесты бизнес-логики (без БД и HTTP)

```bash
cd php-orders-service
composer install
php tests/SmokeTest.php
```

Проверяет: расчёт скидок через Strategy+Factory, публикацию событий
через Observer, доменные инварианты (`Order::confirm()` на пустом
заказе бросает исключение, неизвестный `tier` в Factory бросает
исключение).

## Проверка типов и стиля фронтенда

```bash
cd frontend
npm install
npm run type-check   # только проверка типов (tsc --noEmit)
npm run lint          # eslint . — стиль кода, правила React Hooks
npm run build         # tsc -b && vite build — соберёт, если типы верны
```

Типы в `src/types/order.ts` — намеренно ручное зеркало JSON-ответов
PHP и Go сервисов (а не сгенерированные автоматически), чтобы было
видно весь контракт в одном файле.

ESLint настроен через flat config (`eslint.config.js`, ESLint 9) с
`typescript-eslint` (рекомендованный набор правил для `.ts`/`.tsx`),
`eslint-plugin-react-hooks` (защита от нарушений правил хуков —
условный/циклический вызов `useState`/`useEffect`) и
`eslint-plugin-react-refresh` (совместимость с Vite Fast Refresh).
`@typescript-eslint/no-explicit-any` включён как `error` — `any` в
проекте не используется (см. `unknown` + сужение типа в `catch` в
`api/client.ts` и компонентах).

## Структура проекта

```
php-orders-service/
  src/
    Domain/            — сущности, интерфейсы Strategy/Repository/Event (не знают о БД/HTTP)
    Application/        — Service (use cases), DTO, Factory
    Infrastructure/      — конкретные реализации: Postgres, HTTP-вызов в Go
    Http/                — Controller, Router
  public/index.php       — точка входа, сборка DI-графа
  migrations/            — SQL миграции
  tests/SmokeTest.php    — лёгкий тест бизнес-логики

go-notification-service/
  internal/
    domain/              — Event, интерфейс Channel
    strategy/            — реализации Channel (log, email)
    repository/          — NotificationRepository + Postgres-реализация
    service/             — NotificationService (use case)
    handler/              — HTTP-обработчики
  cmd/server/main.go     — точка входа, сборка зависимостей

gateway/
  nginx.conf             — маршрутизация по префиксам пути

frontend/                  — React + TypeScript (Vite)
  src/
    api/client.ts         — типизированная обёртка над fetch для Gateway
    components/           — CreateOrderForm, OrdersList, NotificationsLog (.tsx)
    types/order.ts        — типы домена, зеркало JSON от PHP/Go сервисов
    App.tsx

postgres-init/           — SQL, выполняемый при первом старте Postgres
docker-compose.yml
```

## Что можно добавить дальше

- **Decorator**: кэширующий слой над `OrderRepositoryInterface`.
- **CQRS-подобное разделение**: отдельный read-model для списка заказов.
- **Очередь вместо HTTP webhook** (RabbitMQ/Kafka) между PHP и Go —
  Observer на стороне PHP останется тем же, поменяется только
  `NotificationServiceListener`.
- **PHPUnit** вместо самодельного `SmokeTest.php`.
