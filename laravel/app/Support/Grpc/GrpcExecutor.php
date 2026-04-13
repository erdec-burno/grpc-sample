<?php

namespace App\Support\Grpc;

class GrpcExecutor
{
    public function __construct(
        private RetryPolicy $retry,
        private CircuitBreaker $breaker,
        private int $timeoutMs = 2000,
    ) {}

    public function execute(callable $fn)
    {
        return $this->breaker->call(function () use ($fn) {
            return $this->retry->execute(function () use ($fn) {
                $start = microtime(true);

                $result = $fn();

                $duration = (microtime(true) - $start) * 1000;
                if ($duration > $this->timeoutMs) {
                    throw new \RuntimeException('gRPC timeout exceeded');
                }

                return $result;
            });
        });
    }
}
