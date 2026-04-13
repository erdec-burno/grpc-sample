<?php

namespace App\Support\Grpc;

class CircuitBreaker
{
    private int $failures = 0;
    private int $lastFailureTime = 0;

    public function __construct(
        private int $threshold = 3,
        private int $timeoutSeconds = 5,
    ) {}

    public function call(callable $fn)
    {
        if ($this->isOpen()) {
            throw new \RuntimeException('Circuit breaker OPEN');
        }

        try {
            $result = $fn();
            $this->reset();
            return $result;
        } catch (\Throwable $e) {
            $this->fail();
            throw $e;
        }
    }

    private function isOpen(): bool
    {
        if ($this->failures < $this->threshold) {
            return false;
        }

        if ((time() - $this->lastFailureTime) > $this->timeoutSeconds) {
            $this->reset();
            return false;
        }

        return true;
    }

    private function fail(): void
    {
        $this->failures++;
        $this->lastFailureTime = time();
    }

    private function reset(): void
    {
        $this->failures = 0;
    }
}
