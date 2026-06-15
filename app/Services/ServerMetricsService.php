<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ServerMetricsService
{
    public function getQuickMetrics()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return [
                'cpu' => 0,
                'cores' => 1,
                'free_ram' => 0,
                'total_ram' => 1
            ];
        } else {
            // Linux Fallback
            $cores = 1;
            if (@is_readable('/proc/cpuinfo')) {
                $cpuinfo = @file_get_contents('/proc/cpuinfo');
                $cores = substr_count((string)$cpuinfo, 'processor') ?: 1;
            }

            $load = @sys_getloadavg();
            if ($load === false) $load = [0, 0, 0];
            
            $cpu = (int)(($load[0] * 100) / $cores);
            if ($cpu > 100) $cpu = 100;

            $totalRam = 0; $freeRam = 0;
            if (@is_readable('/proc/meminfo')) {
                $meminfo = @file_get_contents('/proc/meminfo');
                if (preg_match('/MemTotal:\s+(\d+)/', (string)$meminfo, $m)) $totalRam = $m[1] * 1024;
                if (preg_match('/MemAvailable:\s+(\d+)/', (string)$meminfo, $m)) $freeRam = $m[1] * 1024;
                elseif (preg_match('/MemFree:\s+(\d+)/', (string)$meminfo, $m)) $freeRam = $m[1] * 1024;
            }

            return [
                'cpu' => $cpu,
                'cores' => $cores,
                'free_ram' => (float)$freeRam,
                'total_ram' => (float)$totalRam ?: 1
            ];
        }
    }

    public function getCpuUsage()
    {
        $cached = Cache::get('server_quick_metrics');
        return is_array($cached) ? ($cached['cpu'] ?? 0) : 0;
    }

    public function getCpuCores()
    {
        $cached = Cache::get('server_quick_metrics');
        return is_array($cached) ? ($cached['cores'] ?? 1) : 1;
    }

    public function getRamUsage()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return ['total' => 1, 'used' => 0, 'free' => 1, 'percentage' => 0];
        } else {
            $cached = Cache::get('server_quick_metrics');
            if (is_array($cached) && $cached['total_ram'] > 1) {
                $total = $cached['total_ram'];
                $free = $cached['free_ram'];
                $used = max(0, $total - $free);
                return [
                    'total' => $total, 'used' => $used, 'free' => $free,
                    'percentage' => round($total > 0 ? ($used / $total) * 100 : 0, 2)
                ];
            }

            if (function_exists('shell_exec')) {
                $free = @shell_exec('free -b');
                if ($free) {
                    $lines = explode("\n", trim((string)$free));
                    if (isset($lines[1])) {
                        $parts = preg_split('/\s+/', $lines[1]);
                        $t = (float)($parts[1] ?? 1); $u = (float)($parts[2] ?? 0);
                        return ['total' => $t, 'used' => $u, 'free' => (float)($parts[3] ?? 0), 'percentage' => round(($u / $t) * 100, 2)];
                    }
                }
            }
        }
        return ['total' => 0, 'used' => 0, 'free' => 0, 'percentage' => 0];
    }

    public function getDiskUsage()
    {
        $path = base_path();
        try {
            $total = @disk_total_space($path) ?: 1;
            $free = @disk_free_space($path) ?: 0;
            $used = $total - $free;
            return ['total' => $total, 'used' => $used, 'free' => $free, 'percentage' => round(($used / $total) * 100, 2)];
        } catch (\Exception $e) {
            return ['total' => 1, 'used' => 0, 'free' => 1, 'percentage' => 0];
        }
    }

    public function getProcesses()
    {
        return Cache::remember('server_processes', 60, function() {
            $processes = [];
            $targetApps = ['php', 'nginx', 'apache', 'httpd', 'node', 'mysql', 'sqlservr', 'postgres', 'redis'];
            
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                $pattern = implode('|', $targetApps);
                if (function_exists('shell_exec')) {
                    $output = @shell_exec("ps aux | grep -E '$pattern' | grep -v grep");
                    $lines = explode("\n", trim((string)$output));
                    $totalRam = $this->getRamUsage()['total'] ?? 0;
                    
                    foreach ($lines as $line) {
                        $parts = preg_split('/\s+/', trim($line), 11);
                        if (count($parts) >= 11) {
                            $processes[] = [
                                'pid' => $parts[1], 
                                'name' => basename($parts[10]), 
                                'memory' => ((float)($parts[3] ?? 0) / 100) * $totalRam
                            ];
                        }
                    }
                }
            }

            $sqlApps = ['mysql', 'postgres', 'sqlservr', 'mariadb', 'redis'];
            $categorized = ['internal' => [], 'sql' => []];

            foreach ($processes as $proc) {
                $name = strtolower($proc['name'] ?? '');
                if (!$name) continue;
                
                $isSql = false;
                foreach ($sqlApps as $app) {
                    if (str_contains($name, $app)) {
                        $categorized['sql'][] = $proc; $isSql = true; break;
                    }
                }
                if (!$isSql) {
                    $categorized['internal'][] = $proc;
                }
            }
            return $categorized;
        });
    }

    public function updateCache()
    {
        $metrics = $this->getQuickMetrics();
        if ($metrics) {
            Cache::put('server_quick_metrics', $metrics, 60);
        }
    }

    public function getSystemInfo()
    {
        return [
            'os' => PHP_OS,
            'hostname' => gethostname(),
            'php_version' => PHP_VERSION,
            'is_docker' => @file_exists('/.dockerenv'),
        ];
    }
}
