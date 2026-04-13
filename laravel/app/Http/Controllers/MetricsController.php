<?php

namespace App\Http\Controllers;

use App\Support\Metrics\MetricsCollector;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function __invoke(): Response
    {
        $metrics = MetricsCollector::all();

        $output = '';
        foreach ($metrics as $name => $value) {
            $output .= "# TYPE {$name} counter\n";
            $output .= "{$name} {$value}\n";
        }

        return response($output, 200)
            ->header('Content-Type', 'text/plain');
    }
}
