# grpc-sample

## Генерация контракта

```
MSYS_NO_PATHCONV=1 MSYS2_ARG_CONV_EXCL="*" docker run --rm \
  -v "D:/repos/grpc-sample:/defs" \
  namely/protoc-all \
  -i /defs/contracts \
  -f user/v1/user.proto \
  -l php \
  -o /defs/laravel/app/Grpc
```

