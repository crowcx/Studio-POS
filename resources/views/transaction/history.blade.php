@extends('layouts.app')

@section('title', 'Riwayat Transaksi')
@section('page-title', 'Riwayat Transaksi')

@section('header-actions')
    @if(Auth::user()->role === 'admin')
        <button type="button" class="btn btn-primary" onclick="openModal('financialExportModal')">
            <i class="fas fa-file-invoice-dollar mr-1"></i> Laporan 3 Bulan
        </button>
        <button type="button" class="btn btn-success" id="exportBtn" onclick="openModal('financialExport1MonthModal')">
            <i class="fas fa-file-invoice-dollar mr-1"></i> Laporan 1 Bulan
        </button>
    @endif
@endsection

@section('content')
<div class="card mb-6">
    <div class="card-header flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900">Filter Transaksi</h3>
        <button type="button" class="btn btn-secondary" id="toggleFilterBtn" onclick="toggleFilter()">
            <i class="fas fa-chevron-up mr-1"></i> <span id="filterToggleText">Sembunyikan Filter</span>
        </button>
    </div>
    <div class="card-body" id="filterFormContainer">
        <form method="GET" action="{{ route('transaction.history') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <div class="form-group">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                </div>

                <div class="form-group">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>Semua Status</option>
                        <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Lunas</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Dibatalkan
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Tipe Pelanggan</label>
                    <select name="customer_type" class="form-control">
                        <option value="all" {{ request('customer_type') == 'all' ? 'selected' : '' }}>Semua Tipe</option>
                        <option value="umum" {{ request('customer_type') == 'umum' ? 'selected' : '' }}>Umum</option>
                        <option value="agen1" {{ request('customer_type') == 'agen1' ? 'selected' : '' }}>Agen Reseller
                        </option>
                        <option value="agen2" {{ request('customer_type') == 'agen2' ? 'selected' : '' }}>Agen Grosir
                        </option>
                    </select>
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search mr-1"></i> Terapkan Filter
                </button>
                <a href="{{ route('transaction.history') }}" class="btn btn-warning">
                    <i class="fas fa-redo mr-1"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

@if(Auth::user()->role === 'admin')
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="card">
            <div class="card-body">
                <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wider">Total Transaksi</h3>
                <div class="mt-2">
                    <div class="text-3xl font-bold text-gray-900">{{ $transactions->total() }}</div>
                    <p class="mt-1 text-sm text-gray-600">Jumlah transaksi ditemukan</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wider">Total Pendapatan</h3>
                <div class="mt-2">
                    <div class="text-3xl font-bold text-gray-900">Rp
                        {{ number_format($totalRevenue ?? $transactions->sum('total_amount'), 0, ',', '.') }}</div>
                    <p class="mt-1 text-sm text-gray-600">Total revenue dari transaksi</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wider">Rata-rata Transaksi</h3>
                <div class="mt-2">
                    <div class="text-3xl font-bold text-gray-900">Rp
                        {{ number_format($averageTransaction ?? $transactions->avg('total_amount') ?? 0, 0, ',', '.') }}
                    </div>
                    <p class="mt-1 text-sm text-gray-600">Rata-rata nilai transaksi</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wider">Transaksi Pending</h3>
                <div class="mt-2">
                    <div class="text-3xl font-bold text-yellow-600">{{ $pendingTransactionsCount ?? 0 }}</div>
                    <p class="mt-1 text-sm text-gray-600">Menunggu pembayaran lunas</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Omset Section -->
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Statistik Omset</h3>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Omset 7 Hari Terakhir -->
                <div
                    class="bg-gradient-to-r from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-lg p-6 border border-blue-200 dark:border-blue-700/30">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-300 uppercase tracking-wider">
                                Omset 7 Hari</h3>
                            <div class="mt-2">
                                <div class="text-3xl font-bold text-blue-900 dark:text-blue-200">Rp
                                    {{ number_format($weeklyRevenue ?? 0, 0, ',', '.') }}</div>
                                <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                                    {{ $weeklyStartDate ?? 'Hari ini' }} - {{ $weeklyEndDate ?? '7 hari lalu' }}
                                </p>
                            </div>
                        </div>
                        <div class="text-blue-500 dark:text-blue-400">
                            <i class="fas fa-calendar-week text-3xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 text-sm text-blue-700 dark:text-blue-300">
                        <div class="flex justify-between">
                            <span class="dark:text-blue-200">Transaksi:</span>
                            <span class="font-semibold dark:text-blue-100">{{ $weeklyTransactionCount ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span class="dark:text-blue-200">Rata-rata:</span>
                            <span class="font-semibold dark:text-blue-100">Rp
                                {{ number_format($weeklyAverage ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span class="dark:text-blue-200">Margin:</span>
                            <span class="font-semibold dark:text-blue-100">Rp
                                {{ number_format($weeklyMargin ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Omset Bulan Ini (1-31) -->
                <div
                    class="bg-gradient-to-r from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-lg p-6 border border-green-200 dark:border-green-700/30">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-green-800 dark:text-green-300 uppercase tracking-wider">
                                Omset Bulan Ini</h3>
                            <div class="mt-2">
                                <div class="text-3xl font-bold text-green-900 dark:text-green-200">Rp
                                    {{ number_format($monthlyRevenue ?? 0, 0, ',', '.') }}</div>
                                <p class="mt-1 text-sm text-green-700 dark:text-green-300">
                                    {{ $monthlyPeriod ?? '1' }} - {{ date('d/m/Y') }}
                                </p>
                            </div>
                        </div>
                        <div class="text-green-500 dark:text-green-400">
                            <i class="fas fa-calendar-alt text-3xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 text-sm text-green-700 dark:text-green-300">
                        <div class="flex justify-between">
                            <span class="dark:text-green-200">Transaksi:</span>
                            <span class="font-semibold dark:text-green-100">{{ $monthlyTransactionCount ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span class="dark:text-green-200">Rata-rata:</span>
                            <span class="font-semibold dark:text-green-100">Rp
                                {{ number_format($monthlyAverage ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span class="dark:text-green-200">Margin:</span>
                            <span class="font-semibold dark:text-green-100">Rp
                                {{ number_format($monthlyMargin ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Omset 30 Hari Terakhir -->
                <div
                    class="bg-gradient-to-r from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 rounded-lg p-6 border border-purple-200 dark:border-purple-700/30">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-purple-800 dark:text-purple-300 uppercase tracking-wider">
                                Omset 30 Hari</h3>
                            <div class="mt-2">
                                <div class="text-3xl font-bold text-purple-900 dark:text-purple-200">Rp
                                    {{ number_format($last30DaysRevenue ?? 0, 0, ',', '.') }}</div>
                                <p class="mt-1 text-sm text-purple-700 dark:text-purple-300">
                                    {{ $last30DaysStartDateFormatted ?? 'Hari ini' }} -
                                    {{ $last30DaysEndDateFormatted ?? '30 hari lalu' }}
                                </p>
                            </div>
                        </div>
                        <div class="text-purple-500 dark:text-purple-400">
                            <i class="fas fa-chart-line text-3xl"></i>
                        </div>
                    </div>
                    <div class="mt-4 text-sm text-purple-700 dark:text-purple-300">
                        <div class="flex justify-between">
                            <span class="dark:text-purple-200">Transaksi:</span>
                            <span class="font-semibold dark:text-purple-100">{{ $last30DaysTransactionCount ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span class="dark:text-purple-200">Rata-rata:</span>
                            <span class="font-semibold dark:text-purple-100">Rp
                                {{ number_format($last30DaysAverage ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span class="dark:text-purple-200">Margin:</span>
                            <span class="font-semibold dark:text-purple-100">Rp
                                {{ number_format($last30DaysMargin ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue Breakdown -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                    <h4 class="text-sm font-semibold mb-2 text-gray-800 dark:text-gray-200">Breakdown Status</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Lunas:</span>
                            <span class="font-semibold text-green-600 dark:text-green-400">Rp
                                {{ number_format($paidRevenue ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Uang DP/Pending:</span>
                            <span class="font-semibold text-yellow-600 dark:text-yellow-400">Rp
                                {{ number_format($pendingRevenue ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Keuntungan (vs Bulan Lalu):</span>
                            <span
                                class="font-semibold @if(($dpProfitPercentage ?? 0) >= 0) text-green-600 dark:text-green-400 @else text-red-600 dark:text-red-400 @endif">
                                @if(($dpProfitPercentage ?? 0) >= 0)+@endif{{ number_format($dpProfitPercentage ?? 0, 1) }}%
                            </span>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                    <h4 class="text-sm font-semibold mb-2 text-gray-800 dark:text-gray-200">Breakdown Tipe Pelanggan</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Umum:</span>
                            <span class="font-semibold text-blue-600 dark:text-blue-400">Rp
                                {{ number_format($generalRevenue ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Agen Reseller:</span>
                            <span class="font-semibold text-blue-600 dark:text-blue-400">Rp
                                {{ number_format($agent1Revenue ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Agen Grosir:</span>
                            <span class="font-semibold text-blue-600 dark:text-blue-400">Rp
                                {{ number_format($agent2Revenue ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                    <h4 class="text-sm font-semibold mb-2 text-gray-800 dark:text-gray-200">Breakdown Metode Bayar</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Tunai:</span>
                            <span class="font-semibold text-blue-600 dark:text-blue-400">Rp
                                {{ number_format($cashRevenue ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Transfer:</span>
                            <span class="font-semibold text-blue-600 dark:text-blue-400">Rp
                                {{ number_format($transferRevenue ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">QRIS:</span>
                            <span class="font-semibold text-blue-600 dark:text-blue-400">Rp
                                {{ number_format($qrisRevenue ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="card">
    <div class="card-header flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900">Daftar Transaksi</h3>
        <div class="text-sm text-gray-600">
            Menampilkan {{ $transactions->firstItem() ?? 0 }}-{{ $transactions->lastItem() ?? 0 }} dari
            {{ $transactions->total() }} transaksi
        </div>
    </div>
    <div class="card-body">
        @if($transactions->count() > 0)
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Invoice ID</th>
                            <th>Tanggal & Jam</th>
                            @if(Auth::user()->role === 'admin')
                                <th>Kasir</th>
                            @endif
                            <th>Pelanggan</th>
                            <th>Tipe</th>
                            <th>Metode Bayar</th>
                            <th class="text-right">Total</th>
                            @if(Auth::user()->role === 'admin')
                                <th class="text-right">Margin</th>
                            @endif
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $transaction)
                            <tr>
                                <td class="font-medium">{{ $transaction->invoice_id }}</td>
                                <td>{{ $transaction->created_at->format('d/m/Y H:i') }}</td>
                                @if(Auth::user()->role === 'admin')
                                    <td>
                                        <div class="flex items-center">
                                            <div
                                                class="flex-shrink-0 h-8 w-8 flex items-center justify-center rounded-full bg-blue-100 text-blue-800 font-bold text-sm">
                                                {{ strtoupper(substr($transaction->user->name ?? 'S', 0, 1)) }}
                                            </div>
                                            <div class="ml-2">
                                                <div class="text-sm font-medium">
                                                    @if($transaction->user)
                                                        {{ $transaction->user->name }}
                                                    @else
                                                        <span class="text-gray-400 italic">System/Deleted</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                @endif
                                <td>
                                    <div class="font-medium">{{ $transaction->customer_name ?? 'Pelanggan Umum' }}</div>
                                    @if($transaction->customer_phone)
                                        <div class="text-sm text-gray-600">{{ $transaction->customer_phone }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($transaction->customer_type === 'umum')
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-gray-100 text-gray-800">
                                            Umum
                                        </span>
                                    @elseif($transaction->customer_type === 'agen1')
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-blue-100 text-blue-800">
                                            Agen Reseller
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-purple-100 text-purple-800">
                                            Agen Grosir
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($transaction->payment_method === 'Cash')
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-money-bill-wave mr-1"></i> Tunai
                                        </span>
                                    @elseif($transaction->payment_method === 'Transfer')
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-blue-100 text-blue-800">
                                            <i class="fas fa-university mr-1"></i> Transfer
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-yellow-100 text-yellow-800">
                                            <i class="fas fa-qrcode mr-1"></i> QRIS
                                        </span>
                                    @endif
                                </td>
                                <td class="text-right font-medium text-green-600">Rp
                                    {{ number_format($transaction->total_amount, 0, ',', '.') }}</td>
                                @if(Auth::user()->role === 'admin')
                                    <td class="text-right font-medium text-blue-600">Rp
                                        {{ number_format($transaction->margin ?? 0, 0, ',', '.') }}</td>
                                @endif
                                <td>
                                    @if($transaction->status === 'paid')
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-1"></i> Lunas
                                        </span>
                                    @elseif($transaction->status === 'pending')
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-yellow-100 text-yellow-800">
                                            <i class="fas fa-clock mr-1"></i> Pending
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-red-100 text-red-800">
                                            <i class="fas fa-times-circle mr-1"></i> Dibatalkan
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex gap-2">
                                        <button type="button" class="btn btn-warning text-sm btn-view-details"
                                            data-id="{{ $transaction->id }}">
                                            <i class="fas fa-eye mr-1"></i> Detail
                                        </button>
                                        @if(in_array($transaction->status, ['paid', 'pending']) && (Auth::user()->role === 'admin' || Auth::id() == $transaction->user_id))
                                            <button type="button" class="btn btn-danger text-sm btn-cancel-trans"
                                                data-id="{{ $transaction->id }}">
                                                <i class="fas fa-times-circle mr-1"></i> Batal
                                            </button>
                                        @endif
                                        @if($transaction->status === 'pending' && (Auth::user()->role === 'admin' || Auth::id() == $transaction->user_id))
                                            <button type="button" class="btn btn-success text-sm btn-mark-paid"
                                                data-id="{{ $transaction->id }}">
                                                <i class="fas fa-check-circle mr-1"></i> Lunas
                                            </button>
                                        @endif
                                        @if($transaction->status === 'cancelled' && Auth::user()->role === 'admin' && !$transaction->items->contains('product_id', null))
                                            <button type="button" class="btn btn-primary text-sm btn-redo-trans"
                                                data-id="{{ $transaction->id }}">
                                                <i class="fas fa-undo mr-1"></i> Redo
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 pagination">
                {{ $transactions->links() }}
            </div>
        @else
            <div class="text-center py-8 text-gray-600">
                <div class="text-4xl mb-2"><i class="fas fa-chart-bar"></i></div>
                <p class="text-lg font-medium mb-2">Tidak ada transaksi yang ditemukan</p>
                <p class="text-sm">Coba ubah filter pencarian atau mulai transaksi baru di kasir.</p>
                <a href="{{ route('transaction.index') }}" class="btn btn-primary mt-4">
                    <i class="fas fa-cash-register mr-1"></i> Ke Kasir
                </a>
            </div>
        @endif
    </div>
</div>

<!-- Transaction Details Modal -->
<div class="modal" id="transactionDetailsModal">
    <div class="modal-content" style="max-width: 400px; width: 100%;">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-gray-900">Detail Transaksi</h3>
            <div class="flex gap-2">
                <button type="button" class="btn btn-primary" onclick="printTransactionDetails()">
                    <i class="fas fa-print mr-1"></i> Cetak
                </button>
                <button type="button" onclick="closeModal('transactionDetailsModal')" class="btn btn-danger">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="modal-body" style="padding: 0;">
            <div id="transactionDetailsContent" style="width: 100%;">
                <!-- Details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal" id="receiptModal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-gray-900">Struk Transaksi</h3>
            <div class="flex gap-2">
                <button type="button" class="btn btn-primary" onclick="printReceipt()">
                    <i class="fas fa-print mr-1"></i> Cetak
                </button>
                <button type="button" onclick="closeModal('receiptModal')" class="btn btn-danger">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="modal-body">
            <div id="receiptContent">
                <!-- Receipt will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
    // Combine into a single robust script block
    (function () {
        window.exportToExcel = function () {
            // Deprecated: Export 1-Month is now used instead via Modal
        };

        window.viewReceipt = function (receiptUrl) {
            fetch(receiptUrl)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('receiptContent').innerHTML = html;
                    openModal('receiptModal');
                })
                .catch(err => {
                    console.error('Error loading receipt:', err);
                    alert('Gagal memuat struk transaksi');
                });
        };

        window.printReceipt = function () {
            const receiptContent = document.getElementById('receiptContent');
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Cetak Struk</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                        @media print { body { padding: 0; } .no-print { display: none !important; } }
                    </style>
                </head>
                <body>
                    ${receiptContent.innerHTML}
                    <div class="no-print" style="margin-top: 20px; text-align: center;">
                        <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Cetak</button>
                        <button onclick="window.close()" style="padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">Tutup</button>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
        };

        window.directPrintReceipt = function (receiptUrl) {
            const printWindow = window.open(receiptUrl, '_blank');
            printWindow.onload = function () { printWindow.print(); };
        };

        const filterForm = document.getElementById('filterForm');
        if (filterForm) {
            filterForm.addEventListener('submit', function (e) {
                const startDateInput = document.querySelector('input[name="start_date"]');
                const endDateInput = document.querySelector('input[name="end_date"]');
                if (startDateInput && endDateInput && startDateInput.value && endDateInput.value) {
                    if (new Date(startDateInput.value) > new Date(endDateInput.value)) {
                        e.preventDefault();
                        alert('Tanggal mulai tidak boleh melewati tanggal akhir!');
                        startDateInput.focus();
                    }
                }
            });
            document.querySelectorAll('input[type="date"], select[name="status"], select[name="customer_type"]').forEach(input => {
                input.addEventListener('change', () => filterForm.submit());
            });
        }

        window.currentTransactionId = null;
        window.resizeIframe = function (obj) {
            obj.style.height = (obj.contentWindow.document.documentElement.scrollHeight + 100) + 'px';
        };

        window.viewTransactionDetails = function (transactionId) {
            window.currentTransactionId = transactionId;
            const content = document.getElementById('transactionDetailsContent');
            content.innerHTML = `
                <iframe 
                    src="/transaksi/struk-iframe/${transactionId}" 
                    style="width: 100%; border: none; overflow: auto;"
                    frameborder="0"
                    allowfullscreen
                    onload="resizeIframe(this)"
                ></iframe>`;
            openModal('transactionDetailsModal');
        };

        window.printTransactionDetails = function (transactionId) {
            const id = transactionId || window.currentTransactionId;
            if (!id) return alert('Tidak ada transaksi yang dipilih');
            const printWindow = window.open(`/transaksi/struk-iframe/${id}`, '_blank');
            printWindow.onload = () => printWindow.print();
        };

        window.cancelTransaction = function (transactionId) {
            if (!confirm('Apakah Anda yakin ingin membatalkan transaksi ini? Stok produk akan dikembalikan.')) return;

            fetch(`/transaksi/${transactionId}/cancel`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('Transaksi berhasil dibatalkan!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Gagal membatalkan transaksi.');
                });
        };

        window.markAsPaid = function (transactionId) {
            if (!confirm('Tandai transaksi ini sebagai LUNAS?')) return;
            fetch(`/transaksi/${transactionId}/mark-as-paid`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('Transaksi berhasil ditandai LUNAS!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(err => alert('Gagal memproses.'));
        };

        window.redoTransaction = function (transactionId) {
            if (!confirm('Aksi REDO akan membatalkan status Batal dan memotong kembali stok barang sesuai data transaksi ini. Lanjutkan?')) return;

            fetch(`/transaksi/${transactionId}/redo`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('Transaksi berhasil di-redo kembai ke status sukses!');
                        location.reload();
                    } else {
                        // Tampilkan pesan error spesifik stok jika ada
                        alert(data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Gagal melakukan redo transaksi.');
                });
        };

        window.toggleFilter = function () {
            const cont = document.getElementById('filterFormContainer');
            const btn = document.getElementById('toggleFilterBtn');
            const isHidden = cont.style.display === 'none';
            cont.style.display = isHidden ? 'block' : 'none';
            btn.querySelector('span').textContent = isHidden ? 'Sembunyikan Filter' : 'Tampilkan Filter';
            btn.querySelector('i').className = isHidden ? 'fas fa-chevron-up mr-1' : 'fas fa-chevron-down mr-1';
        };

        document.addEventListener('DOMContentLoaded', function () {
            // Initial filter visibility
            const startDate = document.querySelector('input[name="start_date"]');
            const statusSelect = document.querySelector('select[name="status"]');
            const hasFilter = (startDate && startDate.value) || (statusSelect && statusSelect.value !== 'all');
            if (!hasFilter) {
                const cont = document.getElementById('filterFormContainer');
                if (cont) cont.style.display = 'none';
            }

            // Global click listener for buttons (Event Delegation)
            document.addEventListener('click', function (e) {
                const viewBtn = e.target.closest('.btn-view-details');
                const cancelBtn = e.target.closest('.btn-cancel-trans');
                const markBtn = e.target.closest('.btn-mark-paid');

                if (viewBtn) return window.viewTransactionDetails(viewBtn.dataset.id);
                if (cancelBtn) return window.cancelTransaction(cancelBtn.dataset.id);
                if (markBtn) return window.markAsPaid(markBtn.dataset.id);
                if (e.target.closest('.btn-redo-trans')) return window.redoTransaction(e.target.closest('.btn-redo-trans').dataset.id);
            });
        });
    })();
</script>

<style>
    /* --- Pagination Fix --- */
    .pagination nav {
        display: block !important;
        width: 100% !important;
    }

    /* Hide the mobile-only div by default on desktop */
    .pagination .sm\:hidden {
        display: none !important;
    }

    /* Target the desktop-only container */
    .pagination .hidden.sm\:flex-1 {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        width: 100% !important;
        flex-wrap: wrap !important;
        gap: 1rem !important;
    }

    @media (max-width: 640px) {
        .pagination .sm\:hidden {
            display: flex !important;
            justify-content: space-between !important;
            width: 100% !important;
        }

        .pagination .hidden.sm\:flex-1 {
            display: none !important;
        }
    }

    /* Style the result text */
    .pagination p.text-sm {
        margin: 0 !important;
        color: #4b5563 !important;
    }

    /* Style the page number buttons/links */
    .pagination .inline-flex.shadow-sm {
        display: inline-flex !important;
        border-radius: 0.375rem !important;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
        overflow: hidden !important;
        border: none !important;
    }

    .pagination a,
    .pagination .inline-flex.shadow-sm>span>span,
    .pagination .inline-flex.shadow-sm>a,
    .pagination [aria-current="page"]>span,
    .pagination [aria-disabled="true"]>span {
        position: relative !important;
        display: inline-flex !important;
        align-items: center !important;
        padding: 0.5rem 0.75rem !important;
        font-size: 0.875rem !important;
        font-weight: 500 !important;
        line-height: 1.25rem !important;
        text-decoration: none !important;
        background-color: #fff !important;
        border: 1px solid #d1d5db !important;
        margin-left: -1px !important;
        color: #374151 !important;
        transition: background-color 0.2s !important;
    }

    .pagination a:hover {
        background-color: #f9fafb !important;
    }

    .pagination [aria-current="page"]>span {
        background-color: #3b82f6 !important;
        color: white !important;
        border-color: #3b82f6 !important;
        z-index: 10 !important;
    }

    .pagination [aria-disabled="true"]>span {
        color: #9ca3af !important;
        cursor: not-allowed !important;
        background-color: #f3f4f6 !important;
    }

    /* Fix oversized SVG icons */
    .pagination svg {
        width: 1.25rem !important;
        height: 1.25rem !important;
        display: inline-block !important;
        vertical-align: middle !important;
    }

    /* Mobile buttons style */
    .pagination .sm\:hidden a,
    .pagination .sm\:hidden span {
        border-radius: 0.375rem !important;
        margin: 0 !important;
    }

    /* Dark mode support */
    .dark-mode .pagination a,
    .dark-mode .pagination [aria-disabled="true"]>span {
        background-color: #1f2937 !important;
        border-color: #374151 !important;
        color: #d1d5db !important;
    }

    .dark-mode .pagination a:hover {
        background-color: #374151 !important;
    }

    .dark-mode .pagination p.text-sm {
        color: #9ca3af !important;
    }

    .dark-mode .pagination [aria-current="page"]>span {
        background-color: #3b82f6 !important;
        color: white !important;
    }
</style>

<!-- Financial Export Modal -->
<div id="financialExportModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-gray-900">Export Laporan Keuangan (3 Bulan)</h3>
            <button type="button" class="btn btn-danger" onclick="closeModal('financialExportModal')">×</button>
        </div>
        <form action="{{ route('transaction.export.financial') }}" method="GET">
            <div class="modal-body p-6">
                <p class="mb-4 text-gray-700">Pilih bulan terakhir untuk laporan 3 bulan. <br><small
                        class="text-gray-500">Sistem akan menarik data selama 3 bulan ke belakang dari bulan yang
                        dipilih.</small></p>
                <div class="form-group">
                    <label class="form-label">Bulan Terakhir</label>
                    <input type="month" name="end_month" class="form-control" value="{{ date('Y-m') }}" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    onclick="closeModal('financialExportModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Download Spreadsheet</button>
            </div>
        </form>
    </div>
</div>

<!-- 1-Month Financial Export Modal -->
<div id="financialExport1MonthModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-gray-900">Export Laporan Keuangan (1 Bulan)</h3>
            <button type="button" class="btn btn-danger" onclick="closeModal('financialExport1MonthModal')">×</button>
        </div>
        <form action="{{ route('transaction.export') }}" method="GET">
            <div class="modal-body p-6">
                <p class="mb-4 text-gray-700">Pilih bulan untuk laporan keuangan 1 bulan.</p>
                <div class="form-group">
                    <label class="form-label">Pilih Bulan</label>
                    <input type="month" name="month" class="form-control" value="{{ date('Y-m') }}" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('financialExport1MonthModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Download Spreadsheet</button>
            </div>
        </form>
    </div>
</div>

@endsection