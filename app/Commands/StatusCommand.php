<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\SystemCollector;
use LaravelZero\Framework\Commands\Command;

use function Termwind\render;

class StatusCommand extends Command
{
    protected $signature = 'status {--json : Output as JSON}';

    protected $description = 'Display current system status';

    public function handle(SystemCollector $collector): int
    {
        $stats = $collector->all();

        if ($this->option('json')) {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->renderStatus($stats);

        return self::SUCCESS;
    }

    private function renderStatus(array $stats): void
    {
        $memColor = $this->getColor($stats['memory']['percent'], 70, 85);
        $diskColor = $this->getColor($stats['disk']['percent'], 70, 85);
        $loadColor = $this->getColor($stats['load'][0] * 10, 50, 80);

        render(<<<HTML
            <div class="mx-2 my-1">
                <div class="px-2 py-1 bg-blue-800 text-white font-bold">
                    {$stats['hostname']} SYSTEM STATUS
                </div>

                <div class="flex mt-1">
                    <span class="px-2 {$memColor}">Memory: {$stats['memory']['percent']}% ({$stats['memory']['used_gb']}GB / {$stats['memory']['total_gb']}GB)</span>
                    <span class="px-2">Uptime: {$stats['uptime']}</span>
                </div>

                <div class="flex">
                    <span class="px-2 {$diskColor}">Disk: {$stats['disk']['percent']}% ({$stats['disk']['used']} / {$stats['disk']['total']})</span>
                    <span class="px-2 {$loadColor}">Load: {$stats['load'][0]}, {$stats['load'][1]}, {$stats['load'][2]}</span>
                </div>
            </div>
        HTML);

        $this->newLine();
        $this->info('  TOP PROCESSES');

        $headers = ['Process', 'PID', 'CPU %', 'Mem %', 'RSS'];
        $rows = [];

        foreach ($stats['processes'] as $proc) {
            $rows[] = [
                $proc['command'],
                $proc['pid'],
                $proc['cpu'] . '%',
                $proc['mem'] . '%',
                $this->formatBytes($proc['rss_kb'] * 1024),
            ];
        }

        $this->table($headers, $rows);
    }

    private function getColor(float $value, int $warnThreshold, int $critThreshold): string
    {
        if ($value >= $critThreshold) {
            return 'bg-red-600 text-white';
        }
        if ($value >= $warnThreshold) {
            return 'bg-yellow-600 text-black';
        }
        return 'bg-green-600 text-white';
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        return round($bytes / pow(1024, $power), 1) . ' ' . $units[$power];
    }
}
