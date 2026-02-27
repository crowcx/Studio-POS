@extends('layouts.app')

@section('title', 'Manajemen Produk')
@section('page-title', 'Manajemen Produk')

@section('header-actions')
    @if(Auth::user()->role === 'admin')
    <button type="button" class="btn btn-success" onclick="openModal('createProductModal')">
        + Tambah Produk
    </button>
    @endif
    <a href="{{ route('transaction.index') }}" class="btn btn-primary">
        💰 Ke Kasir
    </a>
@endsection

@section('content')
<div class="card mb-6">
    <div class="card-header flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900">
            <i class="fas fa-filter mr-2"></i> Filter Produk
            <span class="text-sm font-normal text-gray-600 ml-2">
                @if(request('category_id') || request('stock_range') || request('search'))
                (Filter Aktif)
                @endif
            </span>
        </h3>
        <button type="button" class="btn btn-secondary" id="toggleFilterBtn" onclick="toggleFilter()">
            <i class="fas fa-chevron-up mr-1"></i> <span id="filterToggleText">Sembunyikan Filter</span>
        </button>
    </div>
    <div class="card-body" id="filterFormContainer">
        <form method="GET" action="{{ route('products.index') }}" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="category_id" class="form-control">
                        <option value="">Semua Kategori</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Stok</label>
                    <select name="stock_range" class="form-control">
                        <option value="">Semua Stok</option>
                        <option value="low" {{ request('stock_range') == 'low' ? 'selected' : '' }}>Stok Rendah (<20)</option>
                        <option value="medium" {{ request('stock_range') == 'medium' ? 'selected' : '' }}>Stok Sedang (20-100)</option>
                        <option value="high" {{ request('stock_range') == 'high' ? 'selected' : '' }}>Stok Tinggi (>100)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Cari Produk</label>
                    <input type="text" name="search" id="searchInput" class="form-control" placeholder="Nama produk, tipe, barcode" value="{{ request('search') }}">
                    <small class="text-gray-500 text-xs mt-1 block">Tekan Enter atau tunggu 1500ms untuk mencari otomatis</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">&nbsp;</label>
                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-primary flex-1">
                            <i class="fas fa-search mr-1"></i> Terapkan Filter
                        </button>
                        <a href="{{ route('products.index') }}" class="btn btn-warning">
                            <i class="fas fa-redo mr-1"></i> Reset
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card mb-6">
        <div class="card-header flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900">Daftar Produk</h3>
        <div class="text-sm text-gray-600">
            Menampilkan {{ $products->firstItem() ?? 0 }}-{{ $products->lastItem() ?? 0 }} dari {{ $products->total() }} produk
        </div>
    </div>
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
        
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
        
        <div class="table-responsive">
            <table class="table" id="productsTable">
                <thead>
                    <tr>
                        <th>
                            <button type="button" class="sort-btn" data-sort="name" data-direction="{{ request('sort') == 'name_asc' ? 'asc' : (request('sort') == 'name_desc' ? 'desc' : '') }}">
                                Nama Barang
                                <span class="sort-icon">
                                    @if(request('sort') == 'name_asc') ↑
                                    @elseif(request('sort') == 'name_desc') ↓
                                    @endif
                                </span>
                            </button>
                        </th>
                        <th>Kategori</th>
                        <th>Stok</th>
                        @if(Auth::user()->role === 'admin')
                        <th>
                            <button type="button" class="sort-btn" data-sort="buy_price" data-direction="{{ request('sort') == 'buy_price_asc' ? 'asc' : (request('sort') == 'buy_price_desc' ? 'desc' : '') }}">
                                Harga Beli
                                <span class="sort-icon">
                                    @if(request('sort') == 'buy_price_asc') ↑
                                    @elseif(request('sort') == 'buy_price_desc') ↓
                                    @endif
                                </span>
                            </button>
                        </th>
                        @endif
                        <th>
                            <button type="button" class="sort-btn" data-sort="price_general" data-direction="{{ request('sort') == 'price_general_asc' ? 'asc' : (request('sort') == 'price_general_desc' ? 'desc' : '') }}">
                                Harga Umum
                                <span class="sort-icon">
                                    @if(request('sort') == 'price_general_asc') ↑
                                    @elseif(request('sort') == 'price_general_desc') ↓
                                    @endif
                                </span>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="sort-btn" data-sort="price_agent1" data-direction="{{ request('sort') == 'price_agent1_asc' ? 'asc' : (request('sort') == 'price_agent1_desc' ? 'desc' : '') }}">
                                Harga Agen 1
                                <span class="sort-icon">
                                    @if(request('sort') == 'price_agent1_asc') ↑
                                    @elseif(request('sort') == 'price_agent1_desc') ↓
                                    @endif
                                </span>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="sort-btn" data-sort="price_agent2" data-direction="{{ request('sort') == 'price_agent2_asc' ? 'asc' : (request('sort') == 'price_agent2_desc' ? 'desc' : '') }}">
                                Harga Agen 2
                                <span class="sort-icon">
                                    @if(request('sort') == 'price_agent2_asc') ↑
                                    @elseif(request('sort') == 'price_agent2_desc') ↓
                                    @endif
                                </span>
                            </button>
                        </th>
                        <th width="150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                    <tr data-product-id="{{ $product->id }}">
                        <td>
                            <div class="font-medium">{{ $product->name }}</div>
                            <div class="text-sm text-gray-600">{{ $product->type_color ?? '-' }}</div>
                            @if($product->barcode)
                            <div class="text-xs text-gray-500">Barcode: {{ $product->barcode }}</div>
                            @endif
                        </td>
                        <td>{{ $product->category->name }}</td>
                        <td>
                            <span class="font-medium {{ $product->stock_status_class }}">
                                {{ $product->display_stock }}
                            </span>
                            @if(!$product->isServiceProduct())
                                @if($product->stock <= 5)
                                <div class="text-xs text-red-500">Stok rendah!</div>
                                @elseif($product->stock <= 20)
                                <div class="text-xs text-yellow-500">Stok menipis</div>
                                @endif
                            @else
                            <div class="text-xs text-green-500">Stok tak terbatas</div>
                            @endif
                        </td>
                        @if(Auth::user()->role === 'admin')
                        <td class="font-medium">Rp {{ number_format($product->buy_price, 0, ',', '.') }}</td>
                        @endif
                        <td class="font-medium">Rp {{ number_format($product->price_general, 0, ',', '.') }}</td>
                        <td class="font-medium">Rp {{ number_format($product->price_agent1, 0, ',', '.') }}</td>
                        <td class="font-medium">Rp {{ number_format($product->price_agent2, 0, ',', '.') }}</td>
                        <td>
                            <div class="flex gap-2">
                                <button type="button" class="btn btn-warning text-sm" onclick="editProduct({{ $product->id }})">
                                    Edit
                                </button>
                                <form action="{{ route('products.destroy', $product->id) }}" method="POST" onsubmit="return confirm('Yakin hapus produk ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger text-sm">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        @if($products->isEmpty())
        <div class="text-center py-8 text-gray-600">
            Tidak ada produk yang ditemukan. Coba ubah filter pencarian atau tambah produk baru.
        </div>
        @endif
        
        <div class="mt-6 pagination">
            {{ $products->links() }}
        </div>
    </div>
</div>

<!-- Create Product Modal -->
<div class="modal" id="createProductModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-gray-900">Tambah Produk Baru</h3>
            <button type="button" onclick="closeModal('createProductModal')" class="btn btn-danger">✕</button>
        </div>
        <form action="{{ route('products.store') }}" method="POST" id="createProductForm">
            @csrf
            <div class="modal-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Nama Produk *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Kategori *</label>
                        <select name="category_id" class="form-control" required>
                            <option value="">Pilih Kategori</option>
                            @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipe/Warna</label>
                        <input type="text" name="type_color" class="form-control" placeholder="Contoh: Merah, 4R, Gold">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Barcode (Opsional)</label>
                        <input type="text" name="barcode" class="form-control" placeholder="Kode barcode">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Stok Awal *</label>
                        <input type="number" name="stock" class="form-control" value="0" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Harga Beli *</label>
                        <input type="number" name="buy_price" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Harga Umum *</label>
                        <input type="number" name="price_general" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Harga Agen 1 *</label>
                        <input type="number" name="price_agent1" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Harga Agen 2 *</label>
                        <input type="number" name="price_agent2" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createProductModal')">Batal</button>
                <button type="submit" class="btn btn-success">Simpan Produk</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal" id="editProductModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-gray-900">Edit Produk</h3>
            <button type="button" onclick="closeModal('editProductModal')" class="btn btn-danger">✕</button>
        </div>
        <form method="POST" id="editProductForm">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div id="editProductInfoBox" class="info-box mb-4" style="display: none;">
                    <strong>⚠️ Produk Jasa</strong><br>
                    Produk ini termasuk kategori <strong id="editServiceCategoryName"></strong> yang memiliki stok tak terbatas.
                    Stok tidak akan berkurang saat transaksi di kasir.
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">Nama Produk *</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Kategori *</label>
                        <select name="category_id" id="edit_category_id" class="form-control" required>
                            <option value="">Pilih Kategori</option>
                            @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipe/Warna (Opsional)</label>
                        <input type="text" name="type_color" id="edit_type_color" class="form-control" placeholder="Contoh: Merah, 4R, Gold">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Barcode (Opsional)</label>
                        <input type="text" name="barcode" id="edit_barcode" class="form-control" placeholder="Kode barcode">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Stok Saat Ini</label>
                        <input type="number" name="current_stock" id="edit_current_stock" class="form-control" readonly style="background-color: #f0f0f0;">
                        <small class="text-gray-600 text-xs mt-1 block">Stok saat ini: <span id="edit_stock_display">0</span></small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Stok Tambahan</label>
                        <input type="number" name="stock_addition" id="edit_stock_addition" class="form-control" value="0" min="0" placeholder="Masukkan jumlah tambahan">
                        <small class="text-gray-600 text-xs mt-1 block">Tambahkan stok baru</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Stok Pengurangan</label>
                        <input type="number" name="stock_reduction" id="edit_stock_reduction" class="form-control" value="0" min="0" placeholder="Masukkan jumlah pengurangan">
                        <small class="text-gray-600 text-xs mt-1 block">Kurangi stok (maks: <span id="edit_max_reduction">0</span>)</small>
                    </div>
                    
                    @if(Auth::user()->role === 'admin')
                    <div class="form-group">
                        <label class="form-label">Harga Beli (Modal) *</label>
                        <input type="number" name="buy_price" id="edit_buy_price" class="form-control" step="0.01" min="0" required>
                    </div>
                    @endif
                    
                    <div class="form-group">
                        <label class="form-label">Harga Umum *</label>
                        <input type="number" name="price_general" id="edit_price_general" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Harga Agen 1 *</label>
                        <input type="number" name="price_agent1" id="edit_price_agent1" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Harga Agen 2 *</label>
                        <input type="number" name="price_agent2" id="edit_price_agent2" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editProductModal')">Batal</button>
                <button type="submit" class="btn btn-success">Update Produk</button>
            </div>
        </form>
    </div>
</div>

<style>
    .grid {
        display: grid;
    }
    
    .grid-cols-1 {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }
    
    @media (min-width: 768px) {
        .md\:grid-cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    
    .gap-4 {
        gap: 1rem;
    }
    
    .text-red-600 {
        color: #dc2626;
    }
    
    .text-red-500 {
        color: #ef4444;
    }
    
    /* Sorting button styles */
    .sort-btn {
        background: none;
        border: none;
        padding: 8px 12px;
        font-weight: 600;
        color: #374151;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 4px;
        width: 100%;
        text-align: left;
    }
    
    .sort-btn:hover {
        background-color: #f3f4f6;
        border-radius: 4px;
    }
    
    .sort-icon {
        font-size: 12px;
        color: #6b7280;
        margin-left: auto;
    }
    
    .sort-btn.active .sort-icon {
        color: #3b82f6;
        font-weight: bold;
    }
    
    th {
        position: relative;
    }
    
    /* Dark mode styles */
    .dark-mode .sort-btn {
        color: #e5e7eb;
    }
    
    .dark-mode .sort-btn:hover {
        background-color: #374151;
    }
    
    .dark-mode .sort-btn.active .sort-icon {
        color: #60a5fa;
    }
    
    .dark-mode .sort-icon {
        color: #9ca3af;
    }
    
    .dark-mode .text-red-600 {
        color: #f87171;
    }
    
    .dark-mode .text-yellow-600 {
        color: #fbbf24;
    }
    
    .dark-mode .text-green-600 {
        color: #34d399;
    }
    
    .dark-mode .text-red-500 {
        color: #fca5a5;
    }
    
    .dark-mode .text-yellow-500 {
        color: #fcd34d;
    }
    
    .dark-mode .table {
        color: #e5e7eb;
    }
    
    .dark-mode .table thead th {
        background-color: #374151;
        border-color: #4b5563;
    }
    
    .dark-mode .table tbody td {
        border-color: #4b5563;
    }
    
    .dark-mode .table tbody tr:hover {
        background-color: #4b5563;
    }
    
    .dark-mode .text-gray-600 {
        color: #9ca3af !important;
    }
    
    .dark-mode .text-gray-500 {
        color: #6b7280 !important;
    }
    
    .dark-mode .form-control {
        background-color: #374151;
        border-color: #4b5563;
        color: #e5e7eb;
    }
    
    .dark-mode .form-control:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
    }
    
    .dark-mode .form-label {
        color: #e5e7eb;
    }
    
    .dark-mode .card {
        background-color: #1f2937;
        border-color: #374151;
    }
    
    .dark-mode .card-header {
        background-color: #374151;
        border-color: #4b5563;
    }
    
    .dark-mode .text-gray-900 {
        color: #f9fafb !important;
    }
    
    .dark-mode .alert-success {
        background-color: #065f46;
        border-color: #047857;
        color: #d1fae5;
    }
    
    .dark-mode .alert-danger {
        background-color: #7f1d1d;
        border-color: #991b1b;
        color: #fecaca;
    }
    
    /* Info box styles */
    .info-box {
        background-color: #e8f4fd;
        border-left: 4px solid #007bff;
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 4px;
    }
    
    .dark-mode .info-box {
        background-color: #1e3a5f;
        border-left-color: #3b82f6;
        color: #dbeafe;
    }
    
    /* Disabled input styles */
    input:disabled,
    select:disabled,
    textarea:disabled {
        background-color: #f0f0f0 !important;
        color: #666 !important;
        cursor: not-allowed !important;
        opacity: 0.7 !important;
    }
    
    .dark-mode input:disabled,
    .dark-mode select:disabled,
    .dark-mode textarea:disabled {
        background-color: #374151 !important;
        color: #9ca3af !important;
        border-color: #4b5563 !important;
    }
    
    /* Specific style for readonly inputs */
    input[readonly] {
        background-color: #f8f9fa !important;
    }
    
    .dark-mode input[readonly] {
        background-color: #374151 !important;
        color: #d1d5db !important;
    }
    
    /* Pagination styles - Fixed to prevent overflow */
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1.5rem;
        max-width: 100%;
        overflow: hidden;
    }
    
    .pagination > nav {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.25rem;
        max-width: 100%;
    }
    
    .pagination .flex {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.25rem;
        max-width: 100%;
    }
    
    .pagination .hidden {
        display: none;
    }
    
    .pagination .items-center {
        align-items: center;
    }
    
    .pagination .justify-between {
        justify-content: space-between;
    }
    
    .pagination .gap-1 {
        gap: 0.25rem;
    }
    
    .pagination .-mt-px {
        margin-top: -1px;
    }
    
    .pagination .relative {
        position: relative;
    }
    
    .pagination .inline-flex {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.5rem;
        height: 2.5rem;
    }
    
    .pagination .items-center {
        align-items: center;
    }
    
    .pagination .px-4 {
        padding-left: 1rem;
        padding-right: 1rem;
    }
    
    .pagination .py-2 {
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }
    
    .pagination .border {
        border-width: 1px;
        border-style: solid;
    }
    
    .pagination .border-gray-300 {
        border-color: #d1d5db;
    }
    
    .pagination .bg-white {
        background-color: #ffffff;
    }
    
    .pagination .text-gray-500 {
        color: #6b7280;
    }
    
    .pagination .text-gray-700 {
        color: #374151;
    }
    
    .pagination .hover\:text-gray-500:hover {
        color: #6b7280;
    }
    
    .pagination .hover\:bg-gray-50:hover {
        background-color: #f9fafb;
    }
    
    .pagination .focus\:z-10:focus {
        z-index: 10;
    }
    
    .pagination .focus\:outline-none:focus {
        outline: 2px solid transparent;
        outline-offset: 2px;
    }
    
    .pagination .focus\:ring-2:focus {
        --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
        --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
        box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    }
    
    .pagination .focus\:ring-blue-500:focus {
        --tw-ring-color: #3b82f6;
    }
    
    .pagination .active\:bg-blue-50:active {
        background-color: #eff6ff;
    }
    
    .pagination .active\:text-blue-600:active {
        color: #2563eb;
    }
    
    /* Pagination link styles */
    .pagination a,
    .pagination span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.5rem;
        height: 2.5rem;
        padding: 0.25rem 0.75rem;
        border: 1px solid #d1d5db;
        background-color: #ffffff;
        color: #374151;
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
        border-radius: 0.375rem;
        transition: all 0.2s;
    }
    
    .pagination a:hover {
        background-color: #f9fafb;
        border-color: #9ca3af;
        color: #1f2937;
    }
    
    .pagination .active span {
        background-color: #3b82f6;
        border-color: #3b82f6;
        color: #ffffff;
        font-weight: 600;
    }
    
    .pagination .disabled span {
        background-color: #f3f4f6;
        border-color: #e5e7eb;
        color: #9ca3af;
        cursor: not-allowed;
    }
    
    /* Dark mode pagination styles */
    .dark-mode .pagination .border-gray-300 {
        border-color: #4b5563;
    }
    
    .dark-mode .pagination .bg-white {
        background-color: #1f2937;
    }
    
    .dark-mode .pagination .text-gray-500 {
        color: #9ca3af;
    }
    
    .dark-mode .pagination .text-gray-700 {
        color: #d1d5db;
    }
    
    .dark-mode .pagination .hover\:text-gray-500:hover {
        color: #9ca3af;
    }
    
    .dark-mode .pagination .hover\:bg-gray-50:hover {
        background-color: #374151;
    }
    
    .dark-mode .pagination .active\:bg-blue-50:active {
        background-color: #1e3a8a;
    }
    
    .dark-mode .pagination .active\:text-blue-600:active {
        color: #60a5fa;
    }
    
    .dark-mode .pagination a,
    .dark-mode .pagination span {
        border-color: #4b5563;
        background-color: #1f2937;
        color: #d1d5db;
    }
    
    .dark-mode .pagination a:hover {
        background-color: #374151;
        border-color: #6b7280;
        color: #f3f4f6;
    }
    
    .dark-mode .pagination .active span {
        background-color: #3b82f6;
        border-color: #3b82f6;
        color: #ffffff;
    }
    
    .dark-mode .pagination .disabled span {
        background-color: #374151;
        border-color: #4b5563;
        color: #6b7280;
    }
</style>

<script>
    // Toggle filter visibility
    function toggleFilter() {
        const filterContainer = document.getElementById('filterFormContainer');
        const toggleBtn = document.getElementById('toggleFilterBtn');
        const toggleText = document.getElementById('filterToggleText');
        const icon = toggleBtn.querySelector('i');
        
        if (filterContainer.style.display === 'none') {
            // Show filter
            filterContainer.style.display = 'block';
            toggleText.textContent = 'Sembunyikan Filter';
            icon.className = 'fas fa-chevron-up mr-1';
        } else {
            // Hide filter
            filterContainer.style.display = 'none';
            toggleText.textContent = 'Tampilkan Filter';
            icon.className = 'fas fa-chevron-down mr-1';
        }
    }
    
    async function editProduct(productId) {
        try {
            const response = await fetch(`/products/${productId}/edit`);
            const html = await response.text();
            
            // Create a temporary div to parse the HTML
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            
            // Extract form data from the edit page
            const form = tempDiv.querySelector('form');
            if (!form) {
                alert('Gagal memuat data produk');
                return;
            }
            
            // Get current stock from the readonly input
            const currentStockInput = tempDiv.querySelector('input[name="current_stock"]');
            const currentStock = currentStockInput ? currentStockInput.value : '0';
            
            // Get category name to check if it's a service product
            const categorySelect = tempDiv.querySelector('select[name="category_id"]');
            const selectedCategoryId = categorySelect ? categorySelect.value : '';
            const selectedOption = categorySelect ? categorySelect.querySelector(`option[value="${selectedCategoryId}"]`) : null;
            const categoryName = selectedOption ? selectedOption.textContent : '';
            
            // Check if this is a service product
            const isServiceProduct = categoryName && ['Jasa cetak', 'Jasa foto, edit, dan pemasangan'].includes(categoryName);
            
            // Show/hide service product info box
            const infoBox = document.getElementById('editProductInfoBox');
            const serviceCategoryName = document.getElementById('editServiceCategoryName');
            
            if (isServiceProduct) {
                infoBox.style.display = 'block';
                serviceCategoryName.textContent = categoryName;
            } else {
                infoBox.style.display = 'none';
            }
            
            // Populate the modal form
            document.getElementById('edit_name').value = tempDiv.querySelector('input[name="name"]')?.value || '';
            document.getElementById('edit_category_id').value = selectedCategoryId;
            document.getElementById('edit_type_color').value = tempDiv.querySelector('input[name="type_color"]')?.value || '';
            document.getElementById('edit_barcode').value = tempDiv.querySelector('input[name="barcode"]')?.value || '';
            document.getElementById('edit_current_stock').value = currentStock;
            document.getElementById('edit_stock_display').textContent = currentStock;
            document.getElementById('edit_stock_addition').value = '0';
            document.getElementById('edit_stock_reduction').value = '0';
            document.getElementById('edit_max_reduction').textContent = currentStock;
            
            // Handle stock reduction field for service products
            const stockReductionInput = document.getElementById('edit_stock_reduction');
            if (isServiceProduct) {
                stockReductionInput.value = '0';
                stockReductionInput.min = '0';
                stockReductionInput.max = '0';
                stockReductionInput.readOnly = true;
                stockReductionInput.style.backgroundColor = '#f0f0f0';
                stockReductionInput.placeholder = 'Tidak berlaku untuk produk jasa';
            } else {
                stockReductionInput.min = '0';
                stockReductionInput.max = currentStock;
                stockReductionInput.readOnly = false;
                stockReductionInput.style.backgroundColor = '';
                stockReductionInput.placeholder = 'Masukkan jumlah pengurangan';
            }
            
            // Get prices - check if elements exist first
            const buyPriceInput = document.getElementById('edit_buy_price');
            if (buyPriceInput) {
                buyPriceInput.value = tempDiv.querySelector('input[name="buy_price"]')?.value || 0;
            }
            
            const priceGeneralInput = document.getElementById('edit_price_general');
            if (priceGeneralInput) {
                priceGeneralInput.value = tempDiv.querySelector('input[name="price_general"]')?.value || 0;
            }
            
            const priceAgent1Input = document.getElementById('edit_price_agent1');
            if (priceAgent1Input) {
                priceAgent1Input.value = tempDiv.querySelector('input[name="price_agent1"]')?.value || 0;
            }
            
            const priceAgent2Input = document.getElementById('edit_price_agent2');
            if (priceAgent2Input) {
                priceAgent2Input.value = tempDiv.querySelector('input[name="price_agent2"]')?.value || 0;
            }
            
            // Update form action
            document.getElementById('editProductForm').action = `/products/${productId}`;
            
            // Show modal
            openModal('editProductModal');
            
        } catch (error) {
            console.error('Error loading product:', error);
            alert('Gagal memuat data produk');
        }
    }
    
    // Handle form submissions
    document.getElementById('createProductForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch(this.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => {
            if (response.ok) {
                window.location.reload();
            } else {
                return response.text().then(text => {
                    throw new Error(text);
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Gagal menyimpan produk');
        });
    });
    
    document.getElementById('editProductForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch(this.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'X-HTTP-Method-Override': 'PUT'
            }
        })
        .then(response => {
            if (response.ok) {
                window.location.reload();
            } else {
                return response.text().then(text => {
                    throw new Error(text);
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Gagal mengupdate produk');
        });
    });
    
    // Initialize filter section state
    document.addEventListener('DOMContentLoaded', function() {
        // Check if any filter is active by checking URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const hasActiveFilter = urlParams.has('category_id') || urlParams.has('stock_range') || urlParams.has('search');
        
        // If no active filter, hide the filter section by default
        if (!hasActiveFilter) {
            const filterContainer = document.getElementById('filterFormContainer');
            const toggleBtn = document.getElementById('toggleFilterBtn');
            const toggleText = document.getElementById('filterToggleText');
            const icon = toggleBtn.querySelector('i');
            
            filterContainer.style.display = 'none';
            toggleText.textContent = 'Tampilkan Filter';
            icon.className = 'fas fa-chevron-down mr-1';
        }
        
        // Auto-submit form when filter inputs change
        document.querySelectorAll('#filterForm select').forEach(select => {
            select.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        });
        
        // Search input with debounce for auto-search
        let searchTimeout;
        const searchInput = document.getElementById('searchInput');
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            
            // Only search if input has at least 2 characters or is empty (to clear filter)
            if (this.value.length >= 2 || this.value.length === 0) {
                searchTimeout = setTimeout(() => {
                    document.getElementById('filterForm').submit();
                }, 1500); // 1500ms delay
            }
        });
        
        // Handle Enter key in search
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchTimeout);
                document.getElementById('filterForm').submit();
            }
        });
        
        // Handle sorting button clicks
        const sortButtons = document.querySelectorAll('.sort-btn');
        
        sortButtons.forEach(button => {
            button.addEventListener('click', function() {
                const sortField = this.getAttribute('data-sort');
                const currentDirection = this.getAttribute('data-direction');
                
                // Toggle direction: none -> asc -> desc -> none
                let newDirection = '';
                let newSortValue = '';
                
                if (currentDirection === '') {
                    newDirection = 'asc';
                    newSortValue = `${sortField}_asc`;
                } else if (currentDirection === 'asc') {
                    newDirection = 'desc';
                    newSortValue = `${sortField}_desc`;
                } else {
                    // If it's desc, remove sorting
                    newDirection = '';
                    newSortValue = '';
                }
                
                // Update all sort buttons to remove active state
                sortButtons.forEach(btn => {
                    btn.classList.remove('active');
                    btn.setAttribute('data-direction', '');
                    const icon = btn.querySelector('.sort-icon');
                    if (icon) icon.textContent = '';
                });
                
                // Update current button
                if (newDirection) {
                    this.classList.add('active');
                    this.setAttribute('data-direction', newDirection);
                    const icon = this.querySelector('.sort-icon');
                    if (icon) icon.textContent = newDirection === 'asc' ? '↑' : '↓';
                }
                
                // Submit form with new sort parameter
                const form = document.getElementById('filterForm');
                const sortInput = document.createElement('input');
                sortInput.type = 'hidden';
                sortInput.name = 'sort';
                sortInput.value = newSortValue;
                
                // Remove existing sort input if any
                const existingSortInput = form.querySelector('input[name="sort"]');
                if (existingSortInput) {
                    existingSortInput.remove();
                }
                
                form.appendChild(sortInput);
                form.submit();
            });
            
            // Set active class for current sort
            const currentDirection = button.getAttribute('data-direction');
            if (currentDirection) {
                button.classList.add('active');
            }
        });
        
        // Auto-focus on first input when modal opens
        const createModal = document.getElementById('createProductModal');
        const editModal = document.getElementById('editProductModal');
        
        createModal.addEventListener('modal.open', function() {
            setTimeout(() => {
                const firstInput = createModal.querySelector('input, select');
                if (firstInput) firstInput.focus();
            }, 100);
        });
        
        editModal.addEventListener('modal.open', function() {
            setTimeout(() => {
                const firstInput = editModal.querySelector('input, select');
                if (firstInput) firstInput.focus();
            }, 100);
        });
        
        // Handle category change in edit modal to update service product status
        const editCategorySelect = document.getElementById('edit_category_id');
        if (editCategorySelect) {
            editCategorySelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const categoryName = selectedOption ? selectedOption.textContent : '';
                const isServiceProduct = categoryName && ['Jasa cetak', 'Jasa foto, edit, dan pemasangan'].includes(categoryName);
                
                const infoBox = document.getElementById('editProductInfoBox');
                const serviceCategoryName = document.getElementById('editServiceCategoryName');
                const stockReductionInput = document.getElementById('edit_stock_reduction');
                const currentStock = document.getElementById('edit_current_stock').value;
                
                if (isServiceProduct) {
                    infoBox.style.display = 'block';
                    serviceCategoryName.textContent = categoryName;
                    
                    // Disable stock reduction for service products
                    stockReductionInput.value = '0';
                    stockReductionInput.min = '0';
                    stockReductionInput.max = '0';
                    stockReductionInput.readOnly = true;
                    stockReductionInput.style.backgroundColor = '#f0f0f0';
                    stockReductionInput.placeholder = 'Tidak berlaku untuk produk jasa';
                } else {
                    infoBox.style.display = 'none';
                    
                    // Enable stock reduction for non-service products
                    stockReductionInput.min = '0';
                    stockReductionInput.max = currentStock;
                    stockReductionInput.readOnly = false;
                    stockReductionInput.style.backgroundColor = '';
                    stockReductionInput.placeholder = 'Masukkan jumlah pengurangan';
                    document.getElementById('edit_max_reduction').textContent = currentStock;
                }
            });
        }
    });
</script>
@endsection