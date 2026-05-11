# Project Status Report

## Что реально работает

### 1. Локальный стенд целиком поднимается через Docker Compose

Есть сервисы:
- `nginx`
- `laravel-app`
- `grpc-user-service`
- `postgres`
- `redis`
- `rabbitmq`
- `jaeger`
- `otel-collector`
- `prometheus`
- `grafana`

Файл: `docker-compose.yml`

### 2. HTTP -> Laravel -> gRPC цепочка для users

Рабочие маршруты:
- `GET /users/{id}`
- `POST /users`

Файл: `laravel/routes/web.php`

Laravel реально вызывает gRPC-клиент:
- `laravel/app/Services/UserGrpcService.php`

### 3. gRPC user-service реально обрабатывает запросы

Есть методы:
- `GetUser`
- `CreateUser`

Файл: `grpc-user-service/server.js`

### 4. Сквозной tracing уже встроен

Сейчас есть:
- HTTP server span в Laravel
- внутренние spans в Laravel для gRPC вызова
- propagation `traceparent` и `tracestate`
- propagation `x-correlation-id`
- continuation tracing в Node gRPC service
- OTLP экспорт через `otel-collector`

Ключевые файлы:
- `laravel/app/Http/Middleware/Traceparent.php`
- `laravel/app/Services/UserGrpcService.php`
- `grpc-user-service/server.js`
- `grpc-user-service/tracing.js`

### 5. Latency breakdown уже есть

В Laravel:
- `metadata`
- `request_build`
- `grpc_call`
- `response_map`
- `total`

В Node:
- `user_lookup`
- `user_build`
- `user_store`
- `total`

### 6. Observability stack подключена

Есть:
- `Jaeger` для traces
- `Prometheus`
- `Grafana`
- `OTel Collector`

Конфиги:
- `infra/otel-collector/config.yml`
- `infra/prometheus/prometheus.yml`

## Работает, но с ограничением

### 1. `grpc-user-service` не использует Postgres как хранилище users

Сейчас users хранятся в памяти:

```js
const users = new Map();
```

Файл: `grpc-user-service/server.js`

Следствия:
- после рестарта данные пропадают
- `CreateUser` не пишет в БД
- `GetUser` не читает из БД

Это главный функциональный предел текущего проекта.

### 2. Laravel — это gateway, а не полноценный user backend

Он:
- валидирует HTTP
- вызывает gRPC
- оформляет ответ
- добавляет tracing

Но доменная логика users живёт не в Laravel.

## Поднято инфраструктурно, но не раскрыто в основном сценарии

### 1. Postgres

Поднят и доступен, но в потоке `/users` сейчас почти не участвует.

### 2. Redis

Есть в стенде, но текущий основной use case на users его не использует.

### 3. RabbitMQ

Есть в стенде, но текущий users-flow на него не завязан.

То есть эти сервисы сейчас скорее часть платформенного окружения, чем реально используемая бизнес-цепочка.

## Что это значит по-честному

Сейчас проект уже хорош как демонстрация:
- HTTP gateway на Laravel
- внутренний gRPC service
- protobuf contracts
- distributed tracing
- локальная observability-инфраструктура

Но он ещё не доведён до полноценного persistence-oriented микросервиса, потому что:
- user-service пока in-memory
- Postgres/Redis/RabbitMQ не участвуют в основном сценарии users как first-class компоненты

## Короткий итог

Проект реально рабочий как:
- demo стенд интеграции Laravel + gRPC + tracing

Проект пока не доведён как:
- полноценный user-service с хранением в Postgres
- сценарий с реальным использованием Redis/RabbitMQ в users pipeline

## Что делать дальше

### 1. Перевести `grpc-user-service` с in-memory хранения на Postgres

Минимальный набор:
- добавить реальное подключение к Postgres в `grpc-user-service`
- создать таблицу `users`
- переписать `CreateUser` на `INSERT`
- переписать `GetUser` на `SELECT`

Это главный шаг, который превратит users-flow из demo в рабочий сервис.

### 2. Развести роли Redis и RabbitMQ

Нужно явно определить, зачем они в проекте:
- `Redis` как cache, session store или rate limiting backend
- `RabbitMQ` как транспорт для событий или асинхронных задач

Сейчас эти сервисы подняты, но их роль в основном сценарии не закреплена.

### 3. Формализовать protobuf workflow

Имеет смысл закрепить:
- где лежит canonical `.proto`
- как генерируются PHP/Node артефакты
- какой командой это обновлять

Без этого contracts слой со временем начнёт расходиться между сервисами.

### 4. Добавить healthcheck и readiness

Минимум:
- HTTP health endpoint в Laravel
- gRPC health endpoint или отдельный health server в `grpc-user-service`
- healthchecks в `docker-compose.yml`

Это упростит локальный запуск и диагностику зависимостей.

### 5. Зафиксировать сценарий observability

Нужно явно описать:
- какие spans считаются основными
- какие атрибуты обязательны
- как смотреть один запрос в Laravel logs, gRPC logs и Jaeger

Технически tracing уже есть, но без формализованного сценария им сложно пользоваться как инструментом диагностики.

### 6. Добавить автоматические тесты по основному потоку

Минимум:
- feature-тесты Laravel на `GET /users/{id}` и `POST /users`
- проверка gRPC integration boundary
- отдельные тесты на ошибки `NOT_FOUND` и transport failures

Сейчас проект больше похож на интеграционный стенд, чем на систему с защищённым поведением.

### 7. Привести документацию к реальному состоянию проекта

Стоит явно задокументировать:
- что Laravel — это gateway
- что user-service сейчас in-memory или уже на Postgres
- как поднимать проект
- как проверять tracing
- какие сервисы реально участвуют в users-flow

Сейчас это знание в основном находится в коде и в рабочих обсуждениях, а не в документации.
