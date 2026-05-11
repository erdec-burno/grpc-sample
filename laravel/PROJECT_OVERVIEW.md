# grpc-sample Laravel project overview

## Purpose

This Laravel 12 app acts as an HTTP gateway in front of an external gRPC user service.
It accepts HTTP requests, enriches them with correlation and tracing metadata, calls the
downstream gRPC backend, and returns JSON responses.

Main request flow:

1. A client sends an HTTP request to Laravel.
2. Global middleware attaches a correlation id and OpenTelemetry trace context.
3. A route or controller calls `App\Services\UserGrpcService`.
4. `UserGrpcService` builds a protobuf request and calls the gRPC user service.
5. The gRPC response is mapped into a regular JSON HTTP response.

## Stack

- PHP `^8.2` in `composer.json`
- Docker image based on `php:8.3-fpm`
- Laravel `^12.0`
- gRPC client: `grpc/grpc`
- Protobuf: `google/protobuf`
- OpenTelemetry: `open-telemetry/api`, `sdk`, `exporter-otlp`

## Entry points

### HTTP entry

Main Laravel entry file:

- `public/index.php`

Application bootstrap:

- `bootstrap/app.php`

`bootstrap/app.php` currently wires:

- web routes from `routes/web.php`
- console commands from `routes/console.php`
- Laravel health endpoint at `/up`
- global middleware:
  - `App\Http\Middleware\CorrelationId`
  - `App\Http\Middleware\Traceparent`

### Nginx and PHP-FPM

Nginx config:

- `docker/nginx/default.conf`

Runtime flow:

1. Nginx listens on port `80`.
2. Static files are served from `public/`.
3. PHP requests are forwarded to `laravel-app:9000`.
4. The PHP-FPM container is built from `Dockerfile`.

## Routes

Only `routes/web.php` is active right now.

### Active routes in `routes/web.php`

- `GET /`
  - returns `resources/views/welcome.blade.php`
- `GET /users/{id}`
  - calls `UserGrpcService::getUser($id)`
  - returns JSON on success
  - returns `404` for `Grpc\STATUS_NOT_FOUND`, otherwise `500`
- `POST /users`
  - validates `name` and `email` inline
  - calls `UserGrpcService::createUser(...)`
  - returns `201` on success
  - disables CSRF with `withoutMiddleware([ValidateCsrfToken::class])`

### Inactive routes in `routes/web.production.php`

There is a more structured route file that is not currently loaded by `bootstrap/app.php`.
That file uses:

- `App\Http\Controllers\UserController`
- `App\Http\Requests\StoreUserRequest`
- `App\Http\Resources\UserResource`
- `App\Support\Grpc\GrpcHttpStatusMapper`

So the repository already contains a cleaner controller/request/resource implementation,
but runtime is still using simple route closures from `routes/web.php`.

## HTTP layer

### `App\Http\Controllers\UserController`

This is the more complete API controller:

- `show(int $id)` gets a user through `UserGrpcService`
- `store(StoreUserRequest $request)` creates a user
- gRPC error codes are translated with `GrpcHttpStatusMapper`
- successful responses are wrapped with `UserResource`

This controller exists but is not used by the active routes.

### `App\Http\Requests\StoreUserRequest`

Validation rules for user creation:

- `name`: required, string
- `email`: required, email

### `App\Http\Resources\UserResource`

Normalizes outgoing JSON to:

- `id`
- `name`
- `email`
- `status`

### `App\Http\Controllers\MetricsController`

This controller exposes metrics in a Prometheus-like text format by reading
`App\Support\Metrics\MetricsCollector`.

Current state:

- no route to this controller was found
- `MetricsCollector::inc()` is not used anywhere

So metrics support exists as scaffolding, but it is not wired into the live app yet.

## Middleware and request context

### `App\Http\Middleware\CorrelationId`

Responsibilities:

- reads `X-Correlation-ID` from the incoming request
- generates a UUID if the header is missing
- stores the value in the container as `correlation_id`
- adds `X-Correlation-ID` to the response

### `App\Http\Middleware\Traceparent`

Responsibilities:

- extracts tracing context from incoming headers
- creates an OpenTelemetry server span for the HTTP request
- stores `traceparent` in the container
- adds `traceparent` and `tracestate` to the response
- marks the span status from the HTTP result

This middleware is what lets the app propagate trace context into gRPC metadata.

## gRPC layer

### `App\Services\UserGrpcService`

This is the main integration service for gRPC.

Responsibilities:

- creates `UserServiceClient`
- reads the gRPC host from `config('services.grpc.user_service_host')`
- uses insecure gRPC credentials
- builds outgoing metadata:
  - `traceparent`
  - `tracestate`
  - `x-correlation-id`
- logs request start and completion
- wraps internal stages in OpenTelemetry spans
- returns a normalized result array:
  - success: `['ok' => true, 'data' => ...]`
  - error: `['ok' => false, 'error' => ..., 'code' => ...]`

Supported operations:

- `getUser(int $id)`
- `createUser(string $name, string $email)`

### Generated protobuf and gRPC classes

Generated client classes live in:

- `app/Grpc/User/V1`
- `app/Grpc/GPBMetadata/User/V1`

Examples:

- `UserServiceClient`
- `GetUserRequest`
- `CreateUserRequest`
- response and message classes

### `App\Support\Grpc\GrpcExecutor`

This wrapper executes gRPC calls through:

1. `CircuitBreaker`
2. `RetryPolicy`
3. a local elapsed-time check

If the call takes longer than `timeoutMs`, it throws:

- `RuntimeException('gRPC timeout exceeded')`

Important detail:

- this is not a real network deadline configured in the gRPC client
- it only checks total elapsed time after the callback returns

### `App\Support\Grpc\RetryPolicy`

Simple retry logic:

- default retries: `3`
- delay: `100ms`
- retries on any `Throwable`

### `App\Support\Grpc\CircuitBreaker`

Simple circuit breaker logic:

- opens after `3` failures
- stays open for `5` seconds
- throws `RuntimeException('Circuit breaker OPEN')` while open

Important detail:

- its state lives inside the PHP object instance
- in a typical PHP-FPM setup this is process-local behavior, not a shared distributed breaker

### `App\Support\Grpc\GrpcHttpStatusMapper`

Maps gRPC status codes to HTTP:

- `NOT_FOUND` -> `404`
- `INVALID_ARGUMENT` -> `422`
- `UNAUTHENTICATED` -> `401`
- `PERMISSION_DENIED` -> `403`
- `UNAVAILABLE` -> `503`
- everything else -> `500`

## Observability

### OpenTelemetry bootstrap

`app/Providers/AppServiceProvider.php` initializes OpenTelemetry only if at least one of
these environment variables exists:

- `OTEL_EXPORTER_OTLP_ENDPOINT`
- `OTEL_EXPORTER_OTLP_TRACES_ENDPOINT`

When enabled, it:

- creates a global tracer provider
- registers `TraceContextPropagator`
- uses `OTEL_SERVICE_NAME`, defaulting to `laravel-app`

If no OTLP endpoint is configured, tracing is effectively disabled.

### Internal spans

`App\Support\Tracing\Span`:

- creates an internal span
- measures duration
- logs `span.completed` or `span.failed`
- records errors into OpenTelemetry

`UserGrpcService` uses this helper around stages such as:

- total execution
- metadata creation
- request building
- gRPC call
- response mapping

### Logging

The code uses Laravel logging directly through `Log::info()`, `Log::warning()`, and
`Log::error()`.

`App\Support\Logging\GrpcLogger` exists, but it is not used by the current code.

## Configuration

### `config/services.php`

Custom config section:

```php
'grpc' => [
    'user_service_host' => env('GRPC_USER_SERVICE_HOST', 'grpc-user-service:50051'),
],
```

Default downstream gRPC target:

- `grpc-user-service:50051`

### `.env`

The `.env` file in this worktree is currently empty.

Important variables for real execution are likely:

- `APP_KEY`
- `GRPC_USER_SERVICE_HOST`
- `OTEL_EXPORTER_OTLP_ENDPOINT` or `OTEL_EXPORTER_OTLP_TRACES_ENDPOINT`
- `OTEL_SERVICE_NAME`

## Database

The repo contains standard Laravel migrations for:

- `users`
- `cache`
- `jobs`

But the main app flow does not currently use the database for user operations.
This app behaves more like a gateway/BFF than the system of record.

## Frontend

Frontend assets are mostly default Laravel scaffolding:

- `resources/views/welcome.blade.php`
- `resources/js/app.js`
- `resources/css/app.css`
- `vite.config.js`

They are not important to the main gRPC user flow.

## Example flow: `GET /users/{id}`

1. The request reaches Laravel through nginx and PHP-FPM.
2. `CorrelationId` sets or propagates `X-Correlation-ID`.
3. `Traceparent` extracts trace context and opens a server span.
4. The route calls `UserGrpcService::getUser($id)`.
5. `UserGrpcService` builds outgoing metadata:
   - `traceparent`
   - `tracestate`
   - `x-correlation-id`
6. A protobuf `GetUserRequest` is created.
7. `UserServiceClient->GetUser(...)->wait()` performs the gRPC call.
8. `GrpcExecutor` wraps the call with retry and circuit breaker logic.
9. The protobuf response is mapped into a simple PHP array.
10. Laravel returns JSON to the client.

## Incomplete or not yet wired pieces

Several parts of the codebase look prepared but not fully active yet:

- `routes/web.production.php` is more mature, but not loaded
- `UserController`, `StoreUserRequest`, and `UserResource` exist, but are not used by active routes
- `MetricsController` exists, but no route points to it
- `MetricsCollector` exists, but counters are not incremented anywhere
- `GrpcLogger` exists, but is unused
- `.env` is empty

## What is needed to run it

At minimum the project needs:

1. a working PHP/Laravel environment or the parent docker-compose stack
2. an available gRPC user backend
3. environment variables
4. installed Composer dependencies

If `grpc-user-service` is not reachable, `/users/{id}` and `POST /users` will fail when
the gRPC call is attempted.

## Summary

This project is a Laravel gateway for a user gRPC service.
It accepts HTTP requests, propagates tracing and correlation context, calls a downstream
gRPC backend, and returns JSON responses.

The repository already includes the next step toward a cleaner architecture:

- controller-based routes
- dedicated request validation
- response resources
- gRPC-to-HTTP status mapping
- basic observability scaffolding

But part of that structure is not connected to the active runtime yet.
