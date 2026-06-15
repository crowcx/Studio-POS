<?php

namespace App\Http\Controllers;

use App\Services\ServerMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ServerPanelController extends Controller
{
    protected $metricsService;

    public function __construct(ServerMetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    public function index()
    {
        try {
            if (Auth::user()->username !== 'admin') {
                abort(403, 'Unauthorized.');
            }

            // Pre-warm the cache if empty to avoid 0s on first load
            if (!Cache::has('server_quick_metrics')) {
                $this->metricsService->updateCache();
            }

            return view('server_panel.index', [
                'cpu' => $this->metricsService->getCpuUsage(),
                'cpu_cores' => $this->metricsService->getCpuCores(),
                'ram' => $this->metricsService->getRamUsage(),
                'disk' => $this->metricsService->getDiskUsage(),
                'processes' => $this->metricsService->getProcesses(),
                'system' => $this->metricsService->getSystemInfo()
            ]);
        } catch (\Throwable $e) {
            return response("Server Panel Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        }
    }

    public function getMetrics()
    {
        try {
            if (Auth::user()->username !== 'admin') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $this->metricsService->updateCache();

            return response()->json([
                'cpu' => $this->metricsService->getCpuUsage(),
                'cpu_cores' => $this->metricsService->getCpuCores(),
                'ram' => $this->metricsService->getRamUsage(),
                'disk' => $this->metricsService->getDiskUsage(),
                'processes' => $this->metricsService->getProcesses(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
