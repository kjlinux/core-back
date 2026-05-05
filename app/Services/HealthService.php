<?php

namespace App\Services;

use App\Models\BiometricDevice;
use App\Models\FeelbackDevice;
use App\Models\RfidDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class HealthService
{
    public const HEARTBEAT_TTL_SECONDS = 90;

    protected MqttService $mqtt;

    public function __construct(MqttService $mqtt)
    {
        $this->mqtt = $mqtt;
    }

    public function snapshot(): array
    {
        $components = [
            'db' => $this->checkDb(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'mqtt' => $this->checkMqtt(),
            'reverb' => $this->checkReverb(),
            'listeners' => [
                'rfid' => $this->checkListener('rfid'),
                'biometric' => $this->checkListener('biometric'),
                'feelback' => $this->checkListener('feelback'),
            ],
        ];

        $statuses = [];
        foreach ($components as $key => $value) {
            if ($key === 'listeners') {
                foreach ($value as $sub) $statuses[] = $sub['status'] ?? 'ok';
            } else {
                $statuses[] = $value['status'] ?? 'ok';
            }
        }

        $hasFail = in_array('fail', $statuses, true);
        $hasDegraded = in_array('degraded', $statuses, true);
        $overall = $hasFail ? 'unhealthy' : ($hasDegraded ? 'degraded' : 'healthy');

        return [
            'status' => $overall,
            'components' => $components,
            'devices' => $this->deviceCounts(),
        ];
    }

    public function deviceCounts(): array
    {
        return [
            'rfid' => [
                'total' => RfidDevice::query()->withoutGlobalScopes()->count(),
                'online' => RfidDevice::query()->withoutGlobalScopes()->where('is_online', true)->count(),
            ],
            'biometric' => [
                'total' => BiometricDevice::query()->withoutGlobalScopes()->count(),
                'online' => BiometricDevice::query()->withoutGlobalScopes()->where('is_online', true)->count(),
            ],
            'feelback' => [
                'total' => FeelbackDevice::query()->withoutGlobalScopes()->count(),
                'online' => FeelbackDevice::query()->withoutGlobalScopes()->where('is_online', true)->count(),
            ],
        ];
    }

    protected function checkDb(): array
    {
        $start = microtime(true);
        try {
            DB::connection()->getPdo();
            DB::select('select 1');
            return ['status' => 'ok', 'latencyMs' => (int) round((microtime(true) - $start) * 1000)];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }

    protected function checkCache(): array
    {
        try {
            $key = 'health:cache:check';
            Cache::put($key, '1', 5);
            $ok = Cache::get($key) === '1';
            return ['status' => $ok ? 'ok' : 'fail'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }

    protected function checkQueue(): array
    {
        try {
            $size = Queue::size();
            return [
                'status' => $size > 1000 ? 'degraded' : 'ok',
                'size' => $size,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'degraded', 'message' => $e->getMessage()];
        }
    }

    protected function checkMqtt(): array
    {
        $start = microtime(true);
        try {
            $client = $this->mqtt->createClient('health-check-' . uniqid());
            $client->publish('core/health/ping', json_encode(['ts' => now()->toIso8601String()]), 0);
            $client->disconnect();
            return ['status' => 'ok', 'latencyMs' => (int) round((microtime(true) - $start) * 1000)];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }

    protected function checkReverb(): array
    {
        $driver = config('broadcasting.default');
        if ($driver === null || $driver === 'log' || $driver === 'null') {
            return ['status' => 'degraded', 'message' => "Broadcasting driver '{$driver}' not enabled for realtime"];
        }
        return ['status' => 'ok', 'driver' => $driver];
    }

    public function listenerHeartbeatKey(string $listener): string
    {
        return "mqtt:listener:{$listener}:heartbeat";
    }

    protected function checkListener(string $listener): array
    {
        $ts = Cache::get($this->listenerHeartbeatKey($listener));
        if (!$ts) {
            return ['status' => 'fail', 'message' => "Listener {$listener} pas de heartbeat"];
        }
        $age = now()->diffInSeconds(\Carbon\Carbon::parse($ts), true);
        if ($age > self::HEARTBEAT_TTL_SECONDS) {
            return ['status' => 'fail', 'message' => "Listener {$listener} stale ({$age}s)"];
        }
        return ['status' => 'ok', 'lastHeartbeatAt' => $ts, 'ageSeconds' => $age];
    }

    public function recordListenerHeartbeat(string $listener): void
    {
        Cache::put($this->listenerHeartbeatKey($listener), now()->toIso8601String(), self::HEARTBEAT_TTL_SECONDS * 3);
    }
}
