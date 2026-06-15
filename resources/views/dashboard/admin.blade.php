@extends('layouts.app')

@section('title', 'Dashboard Admin')
@section('page-title', 'Dashboard Admin')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <!-- Stat Card: Order Hari Ini -->
    <div class="card">
        <div class="card-body">
            <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wider">Order Hari Ini</h3>
            <div class="mt-2">
                <div class="text-3xl font-bold text-gray-900">{{ $todayOrders }}</div>
                <p class="mt-1 text-sm text-gray-600">Total transaksi hari ini</p>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-blue-100 text-blue-800">
                    <i class="fas fa-chart-bar mr-1"></i> Hari Ini
                </span>
            </div>
        </div>
    </div>
    
    <!-- Stat Card: Order Minggu Ini -->
    <div class="card">
        <div class="card-body">
            <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wider">Order Minggu Ini</h3>
            <div class="mt-2">
                <div class="text-3xl font-bold text-gray-900">{{ $weekOrders }}</div>
                <p class="mt-1 text-sm text-gray-600">Total transaksi 7 hari terakhir</p>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-green-100 text-green-800">
                    <i class="fas fa-chart-line mr-1"></i> Minggu Ini
                </span>
            </div>
        </div>
    </div>
    
    <!-- Stat Card: Omset Minggu Ini -->
    <div class="card">
        <div class="card-body">
            <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wider">Omset Minggu Ini</h3>
            <div class="mt-2">
                <div class="text-3xl font-bold text-gray-900">Rp {{ number_format($weekRevenue, 0, ',', '.') }}</div>
                <p class="mt-1 text-sm text-gray-600">Total pendapatan minggu ini</p>
            </div>
            <div class="mt-4">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-purple-100 text-purple-800">
                    <i class="fas fa-money-bill-wave mr-1"></i> Revenue
                </span>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Performance Karyawan -->
    <div class="card">
        <div class="card-header">
            <h3 class="text-lg font-semibold text-gray-900">Performance Karyawan</h3>
        </div>
        <div class="card-body">
            <div class="space-y-4">
                @foreach($employeePerformance as $employee)
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-full bg-blue-100 text-blue-800 font-bold">
                            {{ strtoupper(substr($employee->name, 0, 1)) }}
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900">{{ $employee->name }}</div>
                            <div class="text-sm text-gray-500">Karyawan</div>
                        </div>
                    </div>
                    <div class="flex space-x-6">
                        <div class="text-center mx-3">
                            <div class="text-lg font-bold text-gray-900">{{ $employee->today_transactions }}</div>
                            <div class="text-xs text-gray-500">Hari Ini</div>
                        </div>
                        <div class="text-center mx-3">
                            <div class="text-lg font-bold text-gray-900">{{ $employee->week_transactions }}</div>
                            <div class="text-xs text-gray-500">Minggu Ini</div>
                        </div>
                        <div class="text-center mx-3">
                            <div class="text-lg font-bold text-gray-900">Rp {{ number_format($employee->week_revenue ?? 0, 0, ',', '.') }}</div>
                            <div class="text-xs text-gray-500">Omset</div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    
    <!-- Penjualan per Kategori -->
    <div class="card">
        <div class="card-header">
            <h3 class="text-lg font-semibold text-gray-900">Penjualan per Kategori</h3>
        </div>
        <div class="card-body">
            @if($categorySales->count() > 0)
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th class="text-right">Jumlah</th>
                            <th class="text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categorySales as $category)
                        <tr>
                            <td class="font-medium">{{ $category->category_name }}</td>
                            <td class="text-right font-medium">{{ $category->total_quantity }}</td>
                            <td class="text-right font-medium text-green-600">Rp {{ number_format($category->total_revenue, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-8 text-gray-600">
                <div class="text-4xl mb-2"><i class="fas fa-chart-bar"></i></div>
                <p>Belum ada penjualan minggu ini.</p>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Produk Stok Rendah -->
<div class="card mb-6">
    <div class="card-header flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900">Produk Stok Rendah</h3>
        <a href="{{ route('products.index') }}" class="btn btn-primary text-sm">
            Lihat Semua Produk
        </a>
    </div>
    <div class="card-body">
        @if($lowStockProducts->count() > 0)
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama Produk</th>
                        <th>Kategori</th>
                        <th class="text-right">Stok</th>
                        <th class="text-right">Harga Umum</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lowStockProducts as $product)
                    <tr>
                        <td>
                            <div class="font-medium">{{ $product->name }}</div>
                            <div class="text-sm text-gray-600">{{ $product->type_color ?? '-' }}</div>
                        </td>
                        <td>{{ $product->category->name ?? '-' }}</td>
                        <td class="text-right">
                            <span class="font-medium {{ $product->stock < 5 ? 'text-red-600' : 'text-yellow-600' }}">
                                {{ $product->stock }}
                            </span>
                        </td>
                        <td class="text-right font-medium">Rp {{ number_format($product->price_general, 0, ',', '.') }}</td>
                        <td>
                            @if($product->stock < 5)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-red-100 text-red-800">
                                <i class="fas fa-exclamation-triangle mr-1"></i> Kritis
                            </span>
                            @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-yellow-100 text-yellow-800">
                                <i class="fas fa-exclamation-circle mr-1"></i> Rendah
                            </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-8 text-gray-600">
            <div class="text-4xl mb-2"><i class="fas fa-check-circle text-green-500"></i></div>
            <p>Semua produk memiliki stok yang cukup.</p>
        </div>
        @endif
    </div>
</div>

<!-- Transaksi Terbaru -->
<div class="card">
    <div class="card-header flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900">Transaksi Terbaru</h3>
        <a href="{{ route('transaction.history') }}" class="btn btn-primary text-sm">
            Lihat Semua Transaksi
        </a>
    </div>
    <div class="card-body">
        @if($recentTransactions->count() > 0)
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice ID</th>
                        <th>Tanggal</th>
                        <th>Kasir</th>
                        <th>Pelanggan</th>
                        <th class="text-right">Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentTransactions as $transaction)
                    <tr>
                        <td class="font-medium">{{ $transaction->invoice_id }}</td>
                        <td>{{ $transaction->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $transaction->user->name }}</td>
                        <td>
                            <div class="font-medium">{{ $transaction->customer_name ?? 'Pelanggan Umum' }}</div>
                            <div class="text-sm text-gray-600">
                                @if($transaction->customer_type === 'agen1')
                                    Agen Reseller
                                @elseif($transaction->customer_type === 'agen2')
                                    Agen Grosir
                                @else
                                    {{ ucfirst($transaction->customer_type) }}
                                @endif
                            </div>
                        </td>
                        <td class="text-right font-medium text-green-600">Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</td>
                        <td>
                            @if($transaction->status === 'paid')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-green-100 text-green-800">
                                <i class="fas fa-check-circle mr-1"></i> Lunas
                            </span>
                            @elseif($transaction->status === 'pending')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-yellow-100 text-yellow-800">
                                <i class="fas fa-clock mr-1"></i> Pending
                            </span>
                            @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-red-100 text-red-800">
                                <i class="fas fa-times-circle mr-1"></i> Dibatalkan
                            </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-8 text-gray-600">
            <div class="text-4xl mb-2"><i class="fas fa-shopping-cart"></i></div>
            <p>Belum ada transaksi.</p>
        </div>
        @endif
    </div>
</div>

@endsection