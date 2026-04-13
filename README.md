# grpc-sample

## Конфигурация

Проект использует один общий файл окружения: `/.env` в корне репозитория.

Он используется одновременно:
- `docker compose` для подстановки переменных в `docker-compose.yml`
- Laravel-приложением в `laravel` для CLI и HTTP runtime

Что важно:
- основной файл конфигурации: `D:\repos\grpc-sample\.env`
- шаблон для нового окружения: `D:\repos\grpc-sample\.env.example`
- файл `laravel/.env` больше не используется

Если нужно поднять проект с нуля:
1. Скопируй `/.env.example` в `/.env`, если файла ещё нет.
2. При необходимости измени порты, доступы и хосты в `/.env`.
3. Запусти `docker compose up --build` из корня репозитория.

## Генерация контракта

```bash
MSYS_NO_PATHCONV=1 MSYS2_ARG_CONV_EXCL="*" docker run --rm \
  -v "D:/repos/grpc-sample:/defs" \
  namely/protoc-all \
  -i /defs/contracts \
  -f user/v1/user.proto \
  -l php \
  -o /defs/laravel/app/Grpc
```