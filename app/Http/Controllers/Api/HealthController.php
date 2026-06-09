<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'server_time' => now()->toDateTimeString(),
            'uptime' => round((microtime(true) - LARAVEL_START) / 60, 2) . ' minutes',
            'environment' => app()->environment(),
        ]);
    }
}
