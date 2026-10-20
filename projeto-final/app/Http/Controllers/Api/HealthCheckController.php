<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeploymentProbe;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->databaseIsAvailable(),
            'cache' => $this->cacheIsAvailable(),
            'worker' => DeploymentProbe::query()
                ->where('processed_at', '>=', now()->subMinutes(5))
                ->exists(),
        ];
        $healthy = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'release' => config('app.release'),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function databaseIsAvailable(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function cacheIsAvailable(): bool
    {
        try {
            $token = bin2hex(random_bytes(8));
            Cache::put('deployment-health', $token, 10);

            return Cache::get('deployment-health') === $token;
        } catch (Throwable) {
            return false;
        }
    }
}
