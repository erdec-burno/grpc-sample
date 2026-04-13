<?php

namespace App\Support\Grpc;

class RetryPolicy
{
    public function __construct(
        private int $retries = 3,
        private int $delayMs = 100,
    ) {}

    public function execute(callable $fn)
    {
        $attempt = 0;
        beginning:
        try {
            return $fn();
        } catch (\Throwable $e) {
            if ($attempt >= $this->retries) {
                throw $e;
            }
            $attempt++;
            usleep($this->delayMs * 1000);
            goto beginning;
        }
    }
}
