@extends('layouts.app')

@section('title', 'Backup & Restore')
@section('page-title', 'Backup & Restore Database')

@push('styles')
<style>
    /* Dark mode adjustments specifically for backup page elements */
    .dark-mode .card-header {
        border-bottom-color: rgba(255,255,255,0.1);
    }
    .dark-mode .text-blue-900, 
    .dark-mode .text-blue-700,
    .dark-mode .text-red-900,
    .dark-mode .text-red-700 {
        color: #e2e8f0 !important;
    }
    .dark-mode .bg-blue-50, 
    .dark-mode .bg-red-50 {
        background-color: rgba(255,255,255,0.05) !important;
    }
    .dark-mode .bg-yellow-50 {
        background-color: #2b2100 !important; /* Solid dark yellowish brown for dark mode */
        border-color: #f59e0b !important;
    }
    .dark-mode .text-yellow-800 {
        color: #fbbf24 !important; /* Bright amber/yellow for dark mode */
    }
    .dark-mode .border {
        border-color: rgba(255,255,255,0.1) !important;
    }
</style>
@endpush

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Panel Backup -->
    <div class="card shadow-sm border-0">
        <div class="card-header flex justify-between items-center py-4 px-5">
            <div>
                <h3 class="text-lg font-bold text-gray-900 border-0 m-0">
                    <i class="fas fa-download mr-2 text-indigo-500"></i> Buat Backup Baru
                </h3>
                <p class="text-sm text-gray-500 mt-1">Pilih data yang ingin Anda amankan.</p>
            </div>
            <span class="bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200 text-xs font-bold px-3 py-1 rounded-full uppercase">
                Maks 10 Slot
            </span>
        </div>
        <div class="card-body p-6">
            <form action="{{ route('backup.create') }}" method="POST" id="backupForm" onsubmit="document.getElementById('btnBackup').innerHTML='<i class=\'fas fa-spinner fa-spin mr-2\'></i> Memproses...'; document.getElementById('btnBackup').disabled=true;">
                @csrf
                <div class="space-y-4 mb-6">
                    <label class="flex items-center p-4 border rounded-xl hover:bg-gray-50 transition-colors cursor-pointer group">
                        <input type="checkbox" name="options[]" value="master" class="w-5 h-5 text-indigo-600 rounded" checked>
                        <div class="ml-4">
                            <span class="block font-bold text-gray-900 group-hover:text-indigo-600 transition-colors">Data Master</span>
                            <span class="block text-sm text-gray-500">Produk & Kategori</span>
                        </div>
                    </label>
                    <label class="flex items-center p-4 border rounded-xl hover:bg-gray-50 transition-colors cursor-pointer group">
                        <input type="checkbox" name="options[]" value="operational" class="w-5 h-5 text-indigo-600 rounded" checked>
                        <div class="ml-4">
                            <span class="block font-bold text-gray-900 group-hover:text-indigo-600 transition-colors">Data Transaksi</span>
                            <span class="block text-sm text-gray-500">Riwayat Penjualan, Item, & Booking</span>
                        </div>
                    </label>
                    <label class="flex items-center p-4 border rounded-xl hover:bg-gray-50 transition-colors cursor-pointer group">
                        <input type="checkbox" name="options[]" value="system" class="w-5 h-5 text-indigo-600 rounded" checked>
                        <div class="ml-4">
                            <span class="block font-bold text-gray-900 group-hover:text-indigo-600 transition-colors">Data Sistem</span>
                            <span class="block text-sm text-gray-500">Akun Pengguna & Log Audit</span>
                        </div>
                    </label>
                </div>
                
                <button type="submit" id="btnBackup" class="btn btn-primary w-full py-3 text-lg font-bold shadow-md">
                    <i class="fas fa-save mr-2"></i> Mulai Backup Sekarang
                </button>
            </form>
        </div>
    </div>

    <!-- Panel Restore -->
    <div class="card shadow-sm border-0">
        <div class="card-header py-4 px-5">
            <h3 class="text-lg font-bold text-gray-900 border-0 m-0">
                <i class="fas fa-upload mr-2 text-red-500"></i> Restore Data
            </h3>
            <p class="text-sm text-gray-500 mt-1">Kembalikan sistem ke state sebelumnya menggunakan file ZIP.</p>
        </div>
        <div class="card-body p-6">
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded-r-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-500 text-lg"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-800 leading-relaxed font-medium">
                            <strong>PERINGATAN!</strong> Proses restore akan <strong>menimpa data saat ini</strong>. Disarankan untuk mendownload backup terbaru sebelum melakukan restore!
                        </p>
                    </div>
                </div>
            </div>

            <form action="{{ route('backup.restore') }}" method="POST" enctype="multipart/form-data" id="restoreForm">
                @csrf
                <div class="form-group mb-6">
                    <label class="form-label font-bold text-gray-700">Pilih File Backup (.zip)</label>
                    <div class="mt-2 text-sm text-gray-500 mb-2 italic">*Hanya dukung format .zip hasil export sistem ini.</div>
                    <input type="file" name="backup_file" class="form-control py-2" accept=".zip" required>
                </div>
                
                <button type="button" class="btn btn-danger w-full py-3 text-lg font-bold shadow-md" onclick="confirmRestore()">
                    <i class="fas fa-history mr-2"></i> Restore Data Sekarang
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Tabel Riwayat Backup -->
<div class="card mt-6 shadow-sm border-0">
    <div class="card-header py-4 px-5">
        <h3 class="text-lg font-bold text-gray-900 border-0 m-0">
            <i class="fas fa-history mr-2 text-indigo-500"></i> Riwayat Backup
        </h3>
    </div>
    <div class="card-body p-0">
        @if(count($backups) > 0)
        <div class="table-responsive">
            <table class="table w-full mb-0">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="py-3 px-5 text-left text-xs font-bold uppercase tracking-wider text-gray-600">Nama File</th>
                        <th class="py-3 px-5 text-left text-xs font-bold uppercase tracking-wider text-gray-600">Ukuran</th>
                        <th class="py-3 px-5 text-left text-xs font-bold uppercase tracking-wider text-gray-600">Tanggal Dibuat</th>
                        <th class="py-3 px-5 text-center text-xs font-bold uppercase tracking-wider text-gray-600" width="200">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($backups as $backup)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="py-3 px-5 font-bold text-gray-900">{{ $backup['name'] }}</td>
                        <td class="py-3 px-5 text-gray-600">{{ $backup['size'] }}</td>
                        <td class="py-3 px-5 text-gray-600">{{ \Carbon\Carbon::createFromTimestamp($backup['date'])->format('d M Y, H:i') }}</td>
                        <td class="py-3 px-5">
                            <div class="flex justify-center gap-2">
                                <a href="{{ route('backup.download', $backup['name']) }}" class="btn btn-success text-sm py-1.5 px-3">
                                    <i class="fas fa-download mr-1"></i> Unduh
                                </a>
                                <form action="{{ route('backup.delete', $backup['name']) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus file backup ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger text-sm py-1.5 px-3">
                                        <i class="fas fa-trash mr-1"></i> Hapus
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-12 text-gray-500">
            <div class="text-5xl mb-4 opacity-20"><i class="fas fa-archive"></i></div>
            <p class="font-medium">Belum ada file backup yang tersedia.</p>
        </div>
        @endif
    </div>
</div>

<script>
function confirmRestore() {
    if(confirm('PERINGATAN KRITIS!\n\nApakah Anda YAKIN ingin mengembalikan data?\nSeluruh data yang ada pada sistem saat ini akan ditimpa oleh data dari file backup.\n\nTindakan ini TIDAK BISA DIBATALKAN.')) {
        const btn = document.querySelector('#restoreForm button');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sedang Merestore...';
        btn.disabled = true;
        document.getElementById('restoreForm').submit();
    }
}
</script>
@endsection
