<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\SystemCollector;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;

class HeartbeatCommand extends Command
{
    protected $signature = 'heartbeat
                            {--endpoint= : URL to POST heartbeat to}
                            {--token= : Bearer token for auth}
                            {--silent : Suppress output}';

    protected $description = 'Send system heartbeat to configured endpoint';

    public function handle(SystemCollector $collector): int
    {
        $stats = $collector->all();

        // Add alert levels
        $stats['alerts'] = $this->detectAlerts($stats);
        $stats['status'] = empty($stats['alerts']) ? 'healthy' : 'warning';

        $endpoint = $this->option('endpoint') ?? config('monitor.heartbeat_endpoint');

        if (! $endpoint) {
            if (! $this->option('quiet')) {
                $this->warn('No endpoint configured. Use --endpoint or set MONITOR_HEARTBEAT_ENDPOINT');
                $this->line(json_encode($stats, JSON_PRETTY_PRINT));
            }
            return self::SUCCESS;
        }

        try {
            $request = Http::timeout(10);

            if ($token = $this->option('token') ?? config('monitor.heartbeat_token')) {
                $request = $request->withToken($token);
            }

            $response = $request->post($endpoint, $stats);

            if ($response->successful()) {
                if (! $this->option('quiet')) {
                    $this->info("Heartbeat sent to {$endpoint}");
                }
                return self::SUCCESS;
            }

            $this->error("Heartbeat failed: {$response->status()}");
            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error("Heartbeat failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function detectAlerts(array $stats): array
    {
        $alerts = [];

        if ($stats['memory']['percent'] >= 90) {
            $alerts[] = [
                'level' => 'critical',
                'type' => 'memory',
                'message' => "Memory at {$stats['memory']['percent']}%",
            ];
        } elseif ($stats['memory']['percent'] >= 80) {
            $alerts[] = [
                'level' => 'warning',
                'type' => 'memory',
                'message' => "Memory at {$stats['memory']['percent']}%",
            ];
        }

        if ($stats['disk']['percent'] >= 90) {
            $alerts[] = [
                'level' => 'critical',
                'type' => 'disk',
                'message' => "Disk at {$stats['disk']['percent']}%",
            ];
        } elseif ($stats['disk']['percent'] >= 80) {
            $alerts[] = [
                'level' => 'warning',
                'type' => 'disk',
                'message' => "Disk at {$stats['disk']['percent']}%",
            ];
        }

        if ($stats['load'][0] > 10) {
            $alerts[] = [
                'level' => 'warning',
                'type' => 'load',
                'message' => "Load average: {$stats['load'][0]}",
            ];
        }

        return $alerts;
    }
}
