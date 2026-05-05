<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\HealthService;
use Illuminate\Http\JsonResponse;

class HealthController extends BaseApiController
{
    public function index(HealthService $health): JsonResponse
    {
        return $this->successResponse($health->snapshot() + ['timestamp' => now()->toIso8601String()]);
    }
}
