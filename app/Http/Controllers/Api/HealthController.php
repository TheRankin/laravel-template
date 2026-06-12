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
            'app_version' => config('app.version', 'unknown'),
            'php_version' => phpversion(),
            'environment' => app()->environment(),
            'debug' => config('app.debug'),
        ]);
    }
}
