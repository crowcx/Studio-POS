@extends('layouts.app')

@section('title', 'Audit Log')
@section('page-title', 'Audit Log')

@section('content')
@php
    function getBadgeClass($action) {
        $action = strtolower($action);
        if (str_contains($action, 'added') || str_contains($action, 'created')) return 'bg-green-100 text-green-800';
        if (str_contains($action, 'updated') || str_contains($action, 'edited') || str_contains($action, 'changed') || str_contains($action, 'completed')) return 'bg-yellow-100 text-yellow-800';
        if (str_contains($action, 'transaksi')) return 'bg-blue-100 text-blue-800';
        if (str_contains($action, 'deleted')) return 'bg-red-100 text-red-800';
        return 'bg-gray-100 text-gray-800';
    }
@endphp

<div class="card mb-6">
    <div class="card-header flex items-center justify-between cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors" onclick="toggleAuditFilters()">
        <div class="flex items-center gap-2">
            <i class="fas fa-filter text-gray-500"></i>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Filter Pencarian</h3>
        </div>
        <div class="flex items-center gap-2">
            <span id="filter-status-text" class="text-xs text-gray-500 mr-2">
                {{ request()->anyFilled(['start_date', 'end_date', 'user_id', 'action']) ? 'Filter aktif' : 'Filter siap' }}
            </span>
            <i id="filter-toggle-icon" class="fas fa-chevron-up transition-transform duration-300"></i>
        </div>
    </div>
    <div id="audit-filter-container" class="transition-all duration-300 overflow-hidden">
        <div class="card-body">
            <form method="GET" action="{{ route('audit_logs.index') }}">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="form-group">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" name="start_date" class="form-control" value="{{ request('start_date', now()->subDays(3)->format('Y-m-d')) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" name="end_date" class="form-control" value="{{ request('end_date', now()->format('Y-m-d')) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">User</label>
                    <select name="user_id" class="form-control">
                        <option value="">Semua User</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Aksi Audit</label>
                    <select name="action" class="form-control">
                        <option value="">Semua Aksi</option>
                        @foreach($actions as $actionOpt)
                            <option value="{{ $actionOpt }}" {{ request('action') == $actionOpt ? 'selected' : '' }}>
                                {{ strtoupper(str_replace('_', ' ', $actionOpt)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="submit" class="btn btn-primary">Cari Log</button>
                <a href="{{ route('audit_logs.index') }}" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="text-lg font-semibold text-gray-900">Riwayat Aktivitas Sistem</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th width="150">Waktu</th>
                        <th width="150">User</th>
                        <th width="150">Aksi</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td class="text-sm">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td class="font-medium">{{ $log->user ? $log->user->name : 'System/Deleted' }}</td>
                        <td>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium {{ getBadgeClass($log->action) }}">
                                {{ strtoupper(str_replace('_', ' ', $log->action)) }}
                            </span>
                        </td>
                        <td>
                            <button type="button" class="btn btn-warning text-xs" onclick="showLogDetails({{ $log->id }})">
                                <i class="fas fa-eye mr-1"></i> Lihat Rincian
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-8 text-gray-500">
                            <i class="fas fa-info-circle text-4xl mb-2"></i>
                            <p>Belum ada aktivitas tercatat untuk periode ini.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </div>
</div>

<!-- Log Detail Modal -->
<div id="logDetailModal" class="modal">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-gray-900">Detail Aktivitas</h3>
            <button type="button" class="close" onclick="closeModal('logDetailModal')">&times;</button>
        </div>
        <div class="modal-body" id="logDetailModalBody">
            <!-- Details will be injected here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="closeModal('logDetailModal')">Tutup</button>
        </div>
    </div>
</div>

<script>
    const logsData = @json($logs->keyBy('id'));

    function showLogDetails(logId) {
        const log = logsData[logId];
        if (!log) return;

        const modalBody = document.getElementById('logDetailModalBody');
        modalBody.innerHTML = '';
        
        const details = log.details;
        const action = log.action.toLowerCase();
        
        let html = '<div class="space-y-3">';
        
        html += `<div class="p-3 bg-gray-50 dark:bg-gray-800 rounded border border-gray-100 dark:border-gray-700">`;
        html += `<div class="text-sm text-gray-500 dark:text-gray-400">Aksi:</div>`;
        html += `<div class="font-bold text-gray-900 dark:text-white uppercase">${log.action}</div>`;
        html += `<div class="text-xs text-gray-500 mt-1">${new Date(log.created_at).toLocaleString('id-ID')}</div>`;
        html += `</div>`;

        html += '<ul class="divide-y divide-gray-100 dark:divide-gray-700 border rounded-md overflow-hidden shadow-sm bg-white dark:bg-gray-900">';
        
        if (action.includes('updated') || action.includes('edited') || action.includes('changed') || (details.before && details.after)) {
            const before = details.before || {};
            const after = details.after || {};
            const keys = new Set([...Object.keys(before), ...Object.keys(after)]);
            
            keys.forEach(key => {
                const skipKeys = ['updated_at', 'created_at', 'last_edited_by', 'previous_stock', 'id'];
                if (skipKeys.includes(key)) return;
                
                const bVal = before[key];
                const aVal = after[key];
                
                if (bVal !== aVal) {
                    html += `<li class="log-detail-item-change">
                        <div class="log-detail-header">
                             <span class="log-detail-label-mini">${key.replace(/_/g, ' ')}</span>
                             <div class="log-detail-line"></div>
                        </div>
                        <div class="log-change-container">
                            <div class="log-change-side">
                                <span class="log-change-label">Sebelum</span>
                                <div class="log-value-before">${bVal ?? '-'}</div>
                            </div>
                            <div class="log-change-arrow"><i class="fas fa-arrow-right"></i></div>
                            <div class="log-change-side">
                                <span class="log-change-label">Sesudah</span>
                                <div class="log-value-after">${aVal ?? '-'}</div>
                            </div>
                        </div>
                    </li>`;
                } else {
                     html += `<li class="log-detail-item">
                        <div class="log-detail-label">${key.replace(/_/g, ' ')}</div>
                        <div class="log-detail-value">${aVal ?? '-'}</div>
                    </li>`;
                }
            });
        } else {
            // Created, Deleted, Transaction
            Object.entries(details).forEach(([key, value]) => {
                if (key === 'items' && Array.isArray(value)) {
                    html += `<li class="log-detail-item-column">
                        <div class="log-detail-header">
                             <span class="log-detail-label-mini">ITEMS</span>
                             <div class="log-detail-line"></div>
                        </div>
                        <div class="log-items-grid">`;
                    value.forEach(item => {
                        html += `<div class="log-item-card">
                            <div class="log-item-name">${item.product?.name || item.product_name || 'Produk'}</div>
                            <div class="log-item-details">
                                <div class="log-item-stat">
                                    <span class="log-item-stat-label">Qty & Harga</span>
                                    <span>${item.quantity} x Rp ${item.price?.toLocaleString('id-ID') || item.price_at_transaction?.toLocaleString('id-ID')}</span>
                                </div>
                                <div class="log-item-stat text-right text-right-forced">
                                    <span class="log-item-stat-label">Subtotal</span>
                                    <span class="log-item-total">Rp ${item.subtotal?.toLocaleString('id-ID')}</span>
                                </div>
                            </div>
                        </div>`;
                    });
                    html += `</div></li>`;
                } else if (typeof value === 'object' && value !== null) {
                    html += `<li class="log-detail-item-column">
                        <div class="log-detail-header">
                             <span class="log-detail-label-mini">${key.replace(/_/g, ' ')}</span>
                             <div class="log-detail-line"></div>
                        </div>
                        <pre class="log-detail-json">${JSON.stringify(value, null, 2)}</pre>
                    </li>`;
                } else {
                    html += `<li class="log-detail-item">
                        <div class="log-detail-label">${key.replace(/_/g, ' ')}</div>
                        <div class="log-detail-value">${value ?? '-'}</div>
                    </li>`;
                }
            });
        }
        
        html += '</ul></div>';
        modalBody.innerHTML = html;
        openModal('logDetailModal');
    }
    function toggleAuditFilters() {
        const container = document.getElementById('audit-filter-container');
        const icon = document.getElementById('filter-toggle-icon');
        
        if (container.style.maxHeight === '0px') {
            container.style.maxHeight = '1000px';
            icon.style.transform = 'rotate(0deg)';
            localStorage.setItem('audit_filter_expanded', 'true');
        } else {
            container.style.maxHeight = '0px';
            icon.style.transform = 'rotate(-180deg)';
            localStorage.setItem('audit_filter_expanded', 'false');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('audit-filter-container');
        const icon = document.getElementById('filter-toggle-icon');
        const isExpanded = localStorage.getItem('audit_filter_expanded') !== 'false';
        
        if (!isExpanded && !{{ request()->anyFilled(['user_id', 'action']) ? 'true' : 'false' }}) {
            container.style.maxHeight = '0px';
            icon.style.transform = 'rotate(-180deg)';
        } else {
            container.style.maxHeight = '1000px';
            icon.style.transform = 'rotate(0deg)';
        }
    });
</script>

<style>
    .close {
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1;
        color: #ef4444;
        background: transparent;
        border: 0;
        cursor: pointer;
    }
    
    .rounded-badge {
        border-radius: 4px;
        padding-left: 6px;
        padding-right: 6px;
    }

    .dark-mode .bg-yellow-50 {
        background-color: rgba(253, 224, 71, 0.05);
    }
</style>
@endsection
