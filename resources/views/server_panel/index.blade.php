@extends('layouts.app')

@section('title', 'Server Panel - DTHREE STUDIO')
@section('page-title', 'Server Panel')

@push('styles')
<style>
    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .metric-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        transition: transform 0.2s, box-shadow 0.2s;
        border-top: 4px solid var(--secondary);
    }

    .metric-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    .metric-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .metric-title {
        font-size: 1rem;
        font-weight: 600;
        color: #4b5563;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .metric-value {
        font-size: 2rem;
        font-weight: 700;
        color: #111827;
    }

    .progress-container {
        height: 10px;
        background: #e5e7eb;
        border-radius: 5px;
        margin: 1rem 0;
        overflow: hidden;
    }

    .progress-bar {
        height: 100%;
        border-radius: 5px;
        transition: width 0.5s ease-out;
    }

    .progress-bar.cpu { background: linear-gradient(90deg, #3b82f6, #2563eb); }
    .progress-bar.ram { background: linear-gradient(90deg, #8b5cf6, #7c3aed); }
    .progress-bar.disk { background: linear-gradient(90deg, #10b981, #059669); }

    .metric-details {
        font-size: 0.875rem;
        color: #6b7280;
        display: flex;
        justify-content: space-between;
    }

    .process-section {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
    }

    .process-tabs {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 0.5rem;
    }

    .process-tab {
        padding: 0.5rem 1rem;
        cursor: pointer;
        font-weight: 500;
        color: #6b7280;
        border-radius: 6px;
        transition: all 0.2s;
    }

    .process-tab.active {
        background: var(--secondary);
        color: white;
    }

    .process-table {
        width: 100%;
        border-collapse: collapse;
    }

    .process-table th {
        text-align: left;
        padding: 0.75rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: #374151;
        border-bottom: 1px solid #f3f4f6;
    }

    .process-table td {
        padding: 0.75rem;
        font-size: 0.875rem;
        color: #4b5563;
        border-bottom: 1px solid #f3f4f6;
    }

    .system-info-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        background: #f3f4f6;
        color: #374151;
        margin-right: 0.5rem;
    }

    .badge-docker { background: #dbeafe; color: #1e40af; }
    .badge-os { background: #fee2e2; color: #991b1b; }

    .dark-mode .metric-card, 
    .dark-mode .process-section {
        background: #1f2937;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.3);
    }
    .dark-mode .metric-value,
    .dark-mode .metric-title { color: #f3f4f6; }
    .dark-mode .process-table th { color: #f3f4f6; border-bottom-color: #374151; }
    .dark-mode .process-table td { color: #d1d5db; border-bottom-color: #374151; }
    .dark-mode .process-tabs { border-bottom-color: #374151; }

    .realtime-indicator {
        width: 8px;
        height: 8px;
        background: #10b981;
        border-radius: 50%;
        display: inline-block;
        margin-right: 5px;
        box-shadow: 0 0 8px #10b981;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(1.2); }
        100% { opacity: 1; transform: scale(1); }
    }
</style>
@endpush

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <p class="text-secondary">Informasi kondisi hardware dan sistem secara real-time.</p>
        <div class="mt-2">
            <span class="system-info-badge badge-os"><i class="fas fa-server mr-1"></i> OS: {{ $system['os'] }}</span>
            <span class="system-info-badge" id="core-count-badge"><i class="fas fa-microchip mr-1"></i> Cores: {{ $cpu_cores ?? '?' }}</span>
            @if($system['is_docker'])
                <span class="system-info-badge badge-docker"><i class="fab fa-docker mr-1"></i> Running in Docker</span>
            @endif
            <span class="system-info-badge"><i class="fas fa-desktop mr-1"></i> {{ $system['hostname'] }}</span>
        </div>
    </div>
    <div class="flex items-center">
        <span class="realtime-indicator"></span>
        <span class="text-sm font-medium text-gray-600">Updated: {{ now()->format('H:i:s') }}</span>
    </div>
</div>

<div class="metrics-grid">
    <!-- CPU Usage -->
    <div class="metric-card">
        <div class="metric-header">
            <div class="metric-title"><i class="fas fa-microchip"></i> CPU Usage</div>
            <span id="cpu-percent-badge" class="badge-os" style="padding: 2px 8px; border-radius: 4px; font-size: 10px;">{{ $cpu }}%</span>
        </div>
        <div class="metric-value" id="cpu-value">{{ $cpu }}%</div>
        <div class="progress-container">
            <div class="progress-bar cpu" id="cpu-bar" style="width: {{ $cpu }}%"></div>
        </div>
        <div class="metric-details">
            <span>Load Average</span>
            <span>Real-time</span>
        </div>
    </div>

    <!-- RAM Usage -->
    <div class="metric-card">
        <div class="metric-header">
            <div class="metric-title"><i class="fas fa-memory"></i> Memory (RAM)</div>
            <span class="badge-docker" style="padding: 2px 8px; border-radius: 4px; font-size: 10px;">{{ $ram['percentage'] }}%</span>
        </div>
        <div class="metric-value" id="ram-value">{{ $ram['percentage'] }}%</div>
        <div class="progress-container">
            <div class="progress-bar ram" id="ram-bar" style="width: {{ $ram['percentage'] }}%"></div>
        </div>
        <div class="metric-details">
            <span>Used: {{ number_format($ram['used'] / 1024 / 1024 / 1024, 2) }} GB</span>
            <span>Total: {{ number_format($ram['total'] / 1024 / 1024 / 1024, 2) }} GB</span>
        </div>
    </div>

    <!-- Disk Usage -->
    <div class="metric-card">
        <div class="metric-header">
            <div class="metric-title"><i class="fas fa-hdd"></i> Disk Storage</div>
            <span class="badge-os" style="padding: 2px 8px; border-radius: 4px; font-size: 10px; background: #d1fae5; color: #065f46;">{{ $disk['percentage'] }}%</span>
        </div>
        <div class="metric-value" id="disk-value">{{ $disk['percentage'] }}%</div>
        <div class="progress-container">
            <div class="progress-bar disk" id="disk-bar" style="width: {{ $disk['percentage'] }}%"></div>
        </div>
        <div class="metric-details">
            <span>Used: {{ number_format($disk['used'] / 1024 / 1024 / 1024, 2) }} GB</span>
            <span>Total: {{ number_format($disk['total'] / 1024 / 1024 / 1024, 2) }} GB</span>
        </div>
    </div>
</div>

<div class="process-section">
    <div class="metric-header" style="margin-bottom: 1.5rem;">
        <div class="metric-title"><i class="fas fa-tasks"></i> Active Processes</div>
    </div>
    
    <div class="process-tabs">
        <div class="process-tab active" onclick="showTab('internal')">Internal Apps ({{ count($processes['internal']) }})</div>
        <div class="process-tab" onclick="showTab('sql')">SQL Servers ({{ count($processes['sql']) }})</div>
    </div>

    <div id="tab-internal" class="tab-content">
        <div class="table-responsive">
            <table class="process-table">
                <thead>
                    <tr>
                        <th>PID</th>
                        <th>Process Name</th>
                        <th>Memory Use</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($processes['internal'] as $proc)
                    <tr>
                        <td>{{ $proc['pid'] }}</td>
                        <td><span class="font-semibold">{{ $proc['name'] }}</span></td>
                        <td>{{ isset($proc['memory']) ? number_format($proc['memory'] / 1024 / 1024, 2) . ' MB' : ($proc['memory_percent'] . '%') }}</td>
                        <td><span class="text-success"><i class="fas fa-check-circle"></i> Running</span></td>
                    </tr>
                    @endforeach
                    @if(count($processes['internal']) == 0)
                        <tr><td colspan="4" class="text-center">No internal apps detected.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <div id="tab-sql" class="tab-content" style="display: none;">
        <div class="table-responsive">
            <table class="process-table">
                <thead>
                    <tr>
                        <th>PID</th>
                        <th>Database Engine</th>
                        <th>Memory Use</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($processes['sql'] as $proc)
                    <tr>
                        <td>{{ $proc['pid'] }}</td>
                        <td class="font-semibold text-blue-600">{{ $proc['name'] }}</td>
                        <td>{{ isset($proc['memory']) ? number_format($proc['memory'] / 1024 / 1024, 2) . ' MB' : ($proc['memory_percent'] . '%') }}</td>
                        <td><span class="text-success"><i class="fas fa-database"></i> Connected</span></td>
                    </tr>
                    @endforeach
                    @if(count($processes['sql']) == 0)
                        <tr><td colspan="4" class="text-center">No SQL servers detected. Self-hosting sql?</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    function showTab(tabId) {
        // Update tabs
        document.querySelectorAll('.process-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        event.currentTarget.classList.add('active');

        // Update content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.style.display = 'none';
        });
        document.getElementById('tab-' + tabId).style.display = 'block';
    }

    // Auto refresh every 5 seconds
    async function refreshMetrics() {
        try {
            const response = await fetch('{{ route("server-panel.api") }}');
            if (!response.ok) return;
            const data = await response.json();

            // Update UI
            document.getElementById('cpu-value').innerText = data.cpu + '%';
            document.getElementById('cpu-bar').style.width = data.cpu + '%';
            document.getElementById('cpu-percent-badge').innerText = data.cpu + '%';

            if (data.cpu_cores) {
                document.getElementById('core-count-badge').innerHTML = '<i class="fas fa-microchip mr-1"></i> Cores: ' + data.cpu_cores;
            }

            document.getElementById('ram-value').innerText = data.ram.percentage + '%';
            document.getElementById('ram-bar').style.width = data.ram.percentage + '%';

            document.getElementById('disk-value').innerText = data.disk.percentage + '%';
            document.getElementById('disk-bar').style.width = data.disk.percentage + '%';
            
            // Note: refreshing tables is more complex, just refresh the whole page or keep it simple
        } catch (e) {
            console.error('Failed to refresh metrics', e);
        }
    }

    setInterval(refreshMetrics, 5000);
</script>
@endpush
