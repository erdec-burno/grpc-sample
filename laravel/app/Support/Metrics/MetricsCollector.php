<?php

namespace App\Support\Metrics;

class MetricsCollector
{
    private static array $counters = [];

    public static function inc(string $name): void
    {
        if (!isset(self::$counters[$name])) {
            self::$counters[$name] = 0;
        }
        self::$counters[$name]++;
    }

    public static function all(): array
    {
        return self::$counters;
    }
}
