# grpc-sample

Локальный пример микросервисного окружения с:
- Laravel + Nginx
- gRPC user-service (Node.js)
- PostgreSQL, Redis, RabbitMQ
- Observability: Jaeger, Prometheus, Grafana

## Быстрый старт

1. Подготовьте `.env` (см. переменные в `docker-compose.yml`).
2. Запустите окружение:

```bash
docker compose up --build
```

## Jaeger tracing

Для `grpc-user-service` включён экспорт трейсов через OTLP HTTP.

Переменные окружения сервиса:
- `OTEL_SERVICE_NAME` — имя сервиса в трейcах (по умолчанию `grpc-user-service`)
- `OTEL_EXPORTER_OTLP_ENDPOINT` — базовый OTLP endpoint (например, `http://jaeger:4318`)
- `OTEL_EXPORTER_OTLP_TRACES_ENDPOINT` — полный URL для traces (если задан, имеет приоритет)

Jaeger UI доступен на порту `${JAEGER_UI_PORT}` (по умолчанию обычно `16686`).
