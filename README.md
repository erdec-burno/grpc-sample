# grpc-sample

Локальный стенд для микросервисной схемы с HTTP gateway, gRPC-сервисом и observability stack.

## Состав проекта

- `laravel/` — Laravel-приложение, внешний HTTP gateway
- `grpc-user-service/` — внутренний gRPC user-service на Node.js
- `contracts/` — protobuf-контракты между сервисами
- `infra/` — конфиги OpenTelemetry Collector и Prometheus
- `docker-compose.yml` — сборка и запуск всего стенда

## Архитектура

Основной поток запроса:

1. клиент приходит в `nginx`
2. `nginx` проксирует запрос в `laravel-app`
3. Laravel обрабатывает HTTP и вызывает `grpc-user-service`
4. `grpc-user-service` работает с Postgres
5. ответ возвращается через Laravel обратно клиенту

Observability-поток:

1. `laravel-app` и `grpc-user-service` создают spans
2. трейсы отправляются в `otel-collector`
3. `otel-collector` экспортирует их в `jaeger`
4. `prometheus` и `grafana` используются для метрик и дашбордов

## Сервисы

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

## Текущее состояние

Что уже работает:

- `POST /users`
- `GET /users/{id}`
- Laravel как gRPC-клиент
- `grpc-user-service` с хранением users в Postgres
- сквозной tracing между Laravel и `grpc-user-service`
- `Jaeger`, `Prometheus`, `Grafana` в составе стенда

Что важно понимать:

- Laravel здесь не основной user backend, а HTTP gateway
- user-данные обслуживает `grpc-user-service`
- users сейчас хранятся в таблице `grpc_users`
- `Redis` и `RabbitMQ` подняты как часть окружения, но не являются центральной частью users-flow

## Конфигурация

Проект использует один общий корневой файл `/.env`:

- `docker compose` читает его напрямую
- Laravel использует этот же файл как runtime `.env`

Ключевые переменные:

- `NGINX_PORT`
- `GRPC_USER_SERVICE_HOST`
- `GRPC_USER_SERVICE_PORT`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `OTEL_HTTP_PORT`
- `JAEGER_UI_PORT`
- `GRAFANA_PORT`

## Быстрый старт

1. Подготовьте корневой `.env`
2. Запустите стенд:

```bash
docker compose up --build -d
```

3. Проверьте HTTP endpoint:

```bash
curl http://localhost:8080/users/1
```

4. Создайте пользователя:

```bash
curl -X POST http://localhost:8080/users \
  -H "Content-Type: application/json" \
  -d "{\"name\":\"Alice\",\"email\":\"alice@example.com\"}"
```

## Observability

Интерфейсы:

- Jaeger: `http://localhost:${JAEGER_UI_PORT}`
- Prometheus: `http://localhost:${PROMETHEUS_PORT}`
- Grafana: `http://localhost:${GRAFANA_PORT}`

Grafana по умолчанию:

- login: `admin`
- password: `admin`

Для появления сервисов в Jaeger нужен реальный трафик через приложение. После `POST /users` или `GET /users/{id}` в списке сервисов должны появляться как минимум:

- `laravel-app`
- `grpc-user-service`
- `jaeger-all-in-one`

## Примечания

- `grpc-user-service` использует отдельную таблицу `grpc_users`, а не Laravel-таблицу `users`
- если меняются зависимости Node-сервиса, контейнер `grpc-user-service` нужно пересобирать
- для `grpc-user-service` в Compose выделен отдельный volume под `/app/node_modules`, чтобы bind mount с кодом не затирал контейнерные зависимости
