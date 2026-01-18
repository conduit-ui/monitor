<?php

declare(strict_types=1);

namespace App\Services;

class SystemCollector
{
    public function memory(): array
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            return $this->memoryMac();
        }

        return $this->memoryLinux();
    }

    public function processes(int $limit = 10): array
    {
        $output = shell_exec('ps aux --sort=-%mem 2>/dev/null | head -' . ($limit + 1))
            ?? shell_exec('ps aux -m 2>/dev/null | head -' . ($limit + 1))
            ?? '';

        $lines = array_filter(explode("\n", trim($output)));
        array_shift($lines); // Remove header

        $processes = [];
        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', $line, 11);
            if (count($parts) >= 11) {
                $processes[] = [
                    'user' => $parts[0],
                    'pid' => (int) $parts[1],
                    'cpu' => (float) $parts[2],
                    'mem' => (float) $parts[3],
                    'rss_kb' => (int) $parts[5],
                    'command' => $this->shortenCommand($parts[10]),
                ];
            }
        }

        return $processes;
    }

    public function uptime(): array
    {
        $output = shell_exec('uptime 2>/dev/null') ?? '';

        preg_match('/up\s+(.+?),\s+\d+\s+user/', $output, $matches);
        $uptime = $matches[1] ?? 'unknown';

        preg_match('/load averages?:\s+([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)/', $output, $loadMatches);
        $load = isset($loadMatches[1])
            ? [(float) $loadMatches[1], (float) $loadMatches[2], (float) $loadMatches[3]]
            : [0, 0, 0];

        return [
            'uptime' => trim($uptime),
            'load' => $load,
        ];
    }

    public function disk(): array
    {
        $output = shell_exec('df -h / 2>/dev/null') ?? '';
        $lines = explode("\n", trim($output));

        if (count($lines) < 2) {
            return ['used' => 0, 'total' => 0, 'percent' => 0];
        }

        $parts = preg_split('/\s+/', $lines[1]);

        return [
            'total' => $parts[1] ?? '0',
            'used' => $parts[2] ?? '0',
            'available' => $parts[3] ?? '0',
            'percent' => (int) str_replace('%', '', $parts[4] ?? '0'),
        ];
    }

    public function hostname(): string
    {
        return trim(shell_exec('hostname 2>/dev/null') ?? gethostname());
    }

    public function all(): array
    {
        $memory = $this->memory();
        $uptime = $this->uptime();

        return [
            'hostname' => $this->hostname(),
            'timestamp' => date('c'),
            'memory' => $memory,
            'uptime' => $uptime['uptime'],
            'load' => $uptime['load'],
            'disk' => $this->disk(),
            'processes' => $this->processes(5),
        ];
    }

    private function memoryLinux(): array
    {
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);

        $totalKb = (int) ($total[1] ?? 0);
        $availableKb = (int) ($available[1] ?? 0);
        $usedKb = $totalKb - $availableKb;

        return [
            'total_gb' => round($totalKb / 1024 / 1024, 1),
            'used_gb' => round($usedKb / 1024 / 1024, 1),
            'available_gb' => round($availableKb / 1024 / 1024, 1),
            'percent' => $totalKb > 0 ? round(($usedKb / $totalKb) * 100, 1) : 0,
        ];
    }

    private function memoryMac(): array
    {
        $pageSize = (int) shell_exec('sysctl -n hw.pagesize 2>/dev/null');
        $totalBytes = (int) shell_exec('sysctl -n hw.memsize 2>/dev/null');

        $vmStat = shell_exec('vm_stat 2>/dev/null') ?? '';
        preg_match('/Pages free:\s+(\d+)/', $vmStat, $free);
        preg_match('/Pages inactive:\s+(\d+)/', $vmStat, $inactive);

        $freePages = (int) ($free[1] ?? 0);
        $inactivePages = (int) ($inactive[1] ?? 0);
        $availableBytes = ($freePages + $inactivePages) * $pageSize;
        $usedBytes = $totalBytes - $availableBytes;

        return [
            'total_gb' => round($totalBytes / 1024 / 1024 / 1024, 1),
            'used_gb' => round($usedBytes / 1024 / 1024 / 1024, 1),
            'available_gb' => round($availableBytes / 1024 / 1024 / 1024, 1),
            'percent' => $totalBytes > 0 ? round(($usedBytes / $totalBytes) * 100, 1) : 0,
        ];
    }

    private function shortenCommand(string $command): string
    {
        $command = basename(explode(' ', $command)[0]);
        return strlen($command) > 20 ? substr($command, 0, 20) . '...' : $command;
    }
}
