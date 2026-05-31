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
                foreach ($value as $sub) {
                    $statuses[] = $sub['status'] ?? 'ok';
                }
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
        $cutoff = now()->subMinutes((int) config('devices.offline_threshold_minutes', 5));

        return [
            'rfid' => $this->countKind(RfidDevice::class, 'last_ping_at', $cutoff),
            'biometric' => $this->countKind(BiometricDevice::class, 'last_sync_at', $cutoff),
            'feelback' => $this->countKind(FeelbackDevice::class, 'last_ping_at', $cutoff),
        ];
    }

    /**
     * Compte total + en ligne pour un type de capteur. « En ligne » suit la même
     * définition partout : flag is_online ET dernier signal récent (heartbeat),
     * pour que dashboard, santé système, compagnies et capteurs concordent.
     *
     * @param  class-string  $model
     * @return array{total: int, online: int}
     */
    protected function countKind(string $model, string $timeCol, \Carbon\CarbonInterface $cutoff): array
    {
        return [
            'total' => $model::query()->withoutGlobalScopes()->count(),
            'online' => $model::query()->withoutGlobalScopes()
                ->where('is_online', true)
                ->where($timeCol, '>=', $cutoff)
                ->count(),
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
            $client = $this->mqtt->createClient('health-check-'.uniqid());
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
        if ($driver !== 'reverb') {
            return ['status' => 'degraded', 'message' => "Temps réel désactivé (driver '{$driver}')"];
        }

        // Sonde réelle du serveur Reverb : tester la config seule afficherait « ok »
        // même quand le process Reverb n'est pas lancé. Résultat mis en cache 10s
        // pour ne pas ouvrir une socket à chaque appel de /support/health.
        return Cache::remember('health:reverb:probe', 10, function (): array {
            $host = (string) config('reverb.servers.reverb.host', '127.0.0.1');
            if ($host === '0.0.0.0' || $host === '') {
                $host = '127.0.0.1';
            }
            $port = (int) config('reverb.servers.reverb.port', 8080);

            $conn = @fsockopen($host, $port, $errno, $errstr, 0.5);
            if ($conn === false) {
                return ['status' => 'fail', 'message' => "Serveur Reverb injoignable ({$host}:{$port})"];
            }
            fclose($conn);

            return ['status' => 'ok', 'driver' => 'reverb'];
        });
    }

    public function listenerHeartbeatKey(string $listener): string
    {
        return "mqtt:listener:{$listener}:heartbeat";
    }

    protected function checkListener(string $listener): array
    {
        $ts = Cache::get($this->listenerHeartbeatKey($listener));
        if (! $ts) {
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
