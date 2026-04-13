<?php

namespace App\Support\Grpc;

class GrpcHttpStatusMapper
{
    public static function map(?int $grpcCode): int
    {
        return match ($grpcCode) {
            \Grpc\STATUS_NOT_FOUND => 404,
            \Grpc\STATUS_INVALID_ARGUMENT => 422,
            \Grpc\STATUS_UNAUTHENTICATED => 401,
            \Grpc\STATUS_PERMISSION_DENIED => 403,
            \Grpc\STATUS_UNAVAILABLE => 503,
            default => 500,
        };
    }
}
