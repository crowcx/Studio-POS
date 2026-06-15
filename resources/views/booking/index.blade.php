@extends('layouts.app')

@section('title', 'Booking Studio')
@section('page-title', 'Booking Studio')

@push('styles')
    <style>
        .sort-btn {
            background: none;
            border: none;
            font-weight: 600;
            color: var(--text-main);
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .sort-icon {
            font-size: 12px;
            color: var(--primary);
        }

        .filter-section {
            display: none;
            transition: all 0.3s ease;
        }

        .filter-section.show {
            display: block;
        }

        /* Dark mode adjustments for cards and status */
        body.dark-mode .card {
            background: var(--bg-card);
            border-color: rgba(255, 255, 255, 0.1);
        }

        body.dark-mode .text-gray-800,
        body.dark-mode .text-gray-900 {
            color: #e2e8f0;
        }

        body.dark-mode .text-gray-500,
        body.dark-mode .text-gray-600 {
            color: #94a3b8;
        }

        body.dark-mode .bg-gray-50 {
            background: rgba(0, 0, 0, 0.2) !important;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-600">Daftar Booking Studio</h2>
                <p class="text-gray-500 text-sm">Kelola semua booking studio yang telah dibuat</p>
            </div>
            <a href="{{ route('booking.create') }}" class="btn btn-primary py-2 shadow-sm">
                <i class="fas fa-plus mr-2"></i> Buat Booking Baru
            </a>
        </div>

        <!-- Filter & Sort Form (History Style) -->
        <div class="card mb-6" id="filterCard">
            <div class="card-header flex justify-between items-center cursor-pointer" onclick="toggleFilters()"
                style="padding: 15px 20px;">
                <h3 class="text-lg font-semibold border-b-0"><i class="fas fa-filter mr-2"></i> Filter Pencarian</h3>
                <i class="fas fa-chevron-down transition-transform duration-300" id="filterIcon"
                    style="{{ request()->hasAny(['search', 'month', 'year', 'category', 'status']) ? 'transform: rotate(180deg);' : '' }}"></i>
            </div>
            <div class="card-body" id="filterFormContainer"
                style="{{ request()->hasAny(['search', 'month', 'year', 'category', 'status']) ? 'display: block;' : 'display: none;' }}">
                <form action="{{ route('booking.index') }}" method="GET" id="filterForm">
                    <input type="hidden" name="sort" id="sortField" value="{{ request('sort', 'created_at') }}">
                    <input type="hidden" name="direction" id="sortDirection" value="{{ request('direction', 'desc') }}">

                    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-4">
                        <div class="form-group">
                            <label class="form-label text-sm">Pencarian</label>
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Kode atau Nama..."
                                class="form-control w-full">
                        </div>

                        <div class="form-group">
                            <label class="form-label text-sm">Bulan</label>
                            <select name="month" class="form-control">
                                <option value="">Semua Bulan</option>
                                @for($i = 1; $i <= 12; $i++)
                                    <option value="{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}" {{ request('month') == str_pad($i, 2, '0', STR_PAD_LEFT) ? 'selected' : '' }}>
                                        {{ date('F', mktime(0, 0, 0, $i, 10)) }}
                                    </option>
                                @endfor
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label text-sm">Tahun</label>
                            <select name="year" class="form-control">
                                <option value="">Semua Tahun</option>
                                @for($y = date('Y') - 2; $y <= date('Y') + 1; $y++)
                                    <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label text-sm">Kategori Studio</label>
                            <select name="category" class="form-control">
                                <option value="">Semua Kategori</option>
                                <option value="family_graduation" {{ request('category') == 'family_graduation' ? 'selected' : '' }}>Family / Graduation</option>
                                <option value="prewedding_indoor" {{ request('category') == 'prewedding_indoor' ? 'selected' : '' }}>Prewedding Indoor</option>
                                <option value="studio_outdoor" {{ request('category') == 'studio_outdoor' ? 'selected' : '' }}>Studio Outdoor</option>
                                <option value="sewa_event" {{ request('category') == 'sewa_event' ? 'selected' : '' }}>Sewa
                                    Event</option>
                                <option value="custom" {{ request('category') == 'custom' ? 'selected' : '' }}>Custom</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label text-sm">Status</label>
                            <select name="status" class="form-control">
                                <option value="">Semua Status</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending
                                </option>
                                <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Confirmed
                                </option>
                                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed
                                </option>
                                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search mr-1"></i> Terapkan Filter
                        </button>
                        <a href="{{ route('booking.index') }}" class="btn btn-warning">
                            <i class="fas fa-redo mr-1"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show border-l-4 border-green-500 mb-4" role="alert">
                {{ session('success') }}
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                @if(count($bookings) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover w-full">
                            <thead>
                                <tr>
                                    <th class="py-3 px-4 text-left">Kode Booking</th>
                                    <th class="py-3 px-4 text-left">
                                        <button type="button" class="sort-btn" onclick="applySort('customer_name')">
                                            Pelanggan {!! getSortIcon('customer_name') !!}
                                        </button>
                                    </th>
                                    <th class="py-3 px-4 text-left">
                                        <button type="button" class="sort-btn" onclick="applySort('studio_category')">
                                            Kategori {!! getSortIcon('studio_category') !!}
                                        </button>
                                    </th>
                                    <th class="py-3 px-4 text-left">
                                        <button type="button" class="sort-btn" onclick="applySort('booking_date')">
                                            Tgl Booking {!! getSortIcon('booking_date') !!}
                                        </button>
                                    </th>
                                    <th class="py-3 px-4 text-right font-semibold text-gray-700">Total & DP</th>
                                    <th class="py-3 px-4 text-center font-semibold text-gray-700">Status</th>
                                    <th class="py-3 px-4 text-center font-semibold text-gray-700">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($bookings as $booking)
                                    @php
                                        $isCompleted = $booking->status === 'completed';
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                            'confirmed' => 'bg-blue-100 text-blue-800 border-blue-200',
                                            'completed' => 'bg-green-100 text-green-800 border-green-200',
                                            'cancelled' => 'bg-red-100 text-red-800 border-red-200'
                                        ];
                                        $rowClass = $isCompleted ? 'bg-gray-50 opacity-70' : '';
                                    @endphp
                                    <tr class="{{ $rowClass }} border-b hover:bg-gray-50 transition-colors">
                                        <td class="py-3 px-4">
                                            <strong class="text-gray-900">{{ $booking->booking_code }}</strong><br>
                                            <small class="text-gray-400">Oleh:
                                                @if($booking->user)
                                                    {{ $booking->user->name }}
                                                @else
                                                    <span class="italic">System/Deleted</span>
                                                @endif
                                            </small>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="font-medium text-gray-900">{{ $booking->customer_name }}</div>
                                            <div class="text-xs text-gray-500"><i
                                                    class="fas fa-phone-alt mr-1"></i>{{ $booking->customer_phone }}</div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="font-medium text-gray-600">{{ $booking->studio_category_label }}</div>
                                            <div class="text-xs text-gray-500">{{ $booking->package_type }}</div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div
                                                class="font-medium {{ $booking->booking_date->isPast() && !$isCompleted ? 'text-red-500' : 'text-gray-900' }}">
                                                {{ $booking->booking_date->format('d M Y, H:i') }}
                                            </div>
                                            <div class="text-xs text-gray-500"><i
                                                    class="fas fa-users mr-1"></i>{{ $booking->number_of_people }} orang</div>
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <div class="font-semibold text-gray-900">Total: Rp
                                                {{ number_format($booking->total_amount, 0, ',', '.') }}</div>
                                            <div class="text-xs text-gray-600">DP: Rp
                                                {{ number_format($booking->down_payment, 0, ',', '.') }}</div>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span
                                                class="px-4 py-1.5 rounded-full text-xs font-bold border {{ $statusColors[$booking->status] ?? 'bg-gray-100 text-gray-800 border-gray-200' }}">
                                                @if($isCompleted) <i class="fas fa-check-circle mr-1"></i> @endif
                                                {{ strtoupper($booking->status_label) }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <a href="{{ route('booking.show', $booking) }}"
                                                    class="btn btn-info text-sm py-1 px-2" title="Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('booking.invoice', $booking) }}"
                                                    class="btn btn-primary text-sm py-1 px-2" title="Invoice" target="_blank">
                                                    <i class="fas fa-receipt"></i>
                                                </a>
                                                <a href="{{ route('booking.edit', $booking) }}"
                                                    class="btn btn-warning text-sm py-1 px-2 {{ $isCompleted ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' }}"
                                                    title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                @if(!$isCompleted)
                                                    <form action="{{ route('booking.markAsDone', $booking) }}" method="POST"
                                                        class="inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-success text-sm py-1 px-2"
                                                            title="Selesaikan Booking"
                                                            onclick="return confirm('Apakah booking ini sudah selesai secara aktual di studio?\n\nPerhatian: Anda harus melakukan penagihan via Manajemen Produk Kasir secara mandiri jika ada produk tambahan.')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                                <form action="{{ route('booking.destroy', $booking) }}" method="POST"
                                                    class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger text-sm py-1 px-2" title="Hapus"
                                                        onclick="return confirm('Apakah Anda yakin ingin menghapus booking ini?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 pagination flex justify-center">
                        {{ $bookings->links() }}
                    </div>

                @else
                    <div class="text-center py-10 text-gray-500">
                        <i class="fas fa-calendar-times text-5xl mb-4 text-gray-300"></i><br>
                        <p class="text-xl font-medium text-gray-600 mb-1">Belum ada booking ditemukan</p>
                        <p class="text-sm">Silahkan ubah filter pencarian atau buat booking baru.</p>
                        <a href="{{ route('booking.create') }}" class="btn btn-primary mt-4">Buat Booking Sekarang</a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @php
        function getSortIcon($field)
        {
            if (request('sort') == $field) {
                return request('direction') == 'asc' ? '<span class="sort-icon">▲</span>' : '<span class="sort-icon">▼</span>';
            }
            return '<span class="sort-icon text-gray-300">↕</span>';
        }
    @endphp

@endsection

@push('scripts')
    <script>
        function toggleFilters() {
            const container = document.getElementById('filterFormContainer');
            const icon = document.getElementById('filterIcon');

            if (container.style.display === 'none') {
                container.style.display = 'block';
                icon.style.transform = 'rotate(180deg)';
            } else {
                container.style.display = 'none';
                icon.style.transform = 'rotate(0deg)';
            }
        }

        function applySort(field) {
            const sortFieldInput = document.getElementById('sortField');
            const sortDirInput = document.getElementById('sortDirection');
            const currentSort = sortFieldInput.value;
            const currentDir = sortDirInput.value;

            if (currentSort === field) {
                sortDirInput.value = (currentDir === 'asc') ? 'desc' : 'asc';
            } else {
                sortFieldInput.value = field;
                sortDirInput.value = 'asc';
            }

            document.getElementById('filterForm').submit();
        }
    </script>
@endpush