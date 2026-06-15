<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\SynchronizationController;
use App\Http\Controllers\BookingController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Controllers\ServerPanelController;


// Halaman Login (Bisa diakses siapa saja)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('ratelimit:5,1')->name('login.process');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// API untuk sinkronisasi data antar device
// Note: API routes are excluded from CSRF protection for external device access
// but should include authentication and rate limiting
Route::prefix('api/sync')->middleware(['auth', 'ratelimit:10,1'])->group(function () {
    Route::post('/get-changes', [SynchronizationController::class, 'getChanges']);
    Route::post('/send-changes', [SynchronizationController::class, 'sendChanges']);
});

// Redirect root ke login
Route::get('/', function () { return redirect()->route('login'); });

// Halaman yang butuh Login
Route::middleware('auth')->group(function () {
    // Dashboard berdasarkan role
    Route::get('/dashboard', function () {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->role === 'admin') {
                return redirect()->route('dashboard.admin');
            }
            return redirect()->route('dashboard.employee');
        }
        return redirect()->route('login');
    })->name('dashboard');
    
    Route::get('/dashboard/employee', [DashboardController::class, 'employeeDashboard'])->name('dashboard.employee');
    Route::get('/dashboard/admin', [DashboardController::class, 'adminDashboard'])->name('dashboard.admin');
    
    // Kasir
    Route::get('/kasir', [TransactionController::class, 'index'])->name('transaction.index');
    Route::get('/kasir/products-data', [TransactionController::class, 'getProductsJson'])->name('transaction.products.json');
    Route::post('/kasir/checkout', [TransactionController::class, 'store'])->name('transaction.store');
    Route::get('/transaksi/struk/{transaction}', [TransactionController::class, 'receipt'])->name('transaction.receipt');
    Route::get('/transaksi/struk-iframe/{transaction}', [TransactionController::class, 'receiptIframe'])->name('transaction.receipt.iframe');
    
    // Riwayat Transaksi
    Route::get('/transaksi/riwayat', [TransactionController::class, 'history'])->name('transaction.history');
    Route::get('/transaksi/riwayat/export', [TransactionController::class, 'export'])->name('transaction.export');
    Route::get('/transaksi/riwayat/export-financial', [TransactionController::class, 'exportFinancialReport'])->name('transaction.export.financial');
    Route::post('/transaksi/{transaction}/cancel', [TransactionController::class, 'cancel'])->name('transaction.cancel');
    Route::post('/transaksi/{transaction}/mark-as-paid', [TransactionController::class, 'markAsPaid'])->name('transaction.markAsPaid');
    Route::post('/transaksi/{transaction}/redo', [TransactionController::class, 'redoTransaction'])->name('transaction.redo');
    
    // Pengaturan
    Route::get('/pengaturan', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/pengaturan/ubah-pin', [SettingsController::class, 'changePin'])->name('settings.changePin');
    
    // Manajemen Produk (Stock Manager) - Bisa diakses semua karyawan
    Route::resource('products', ProductController::class);
    
    // Manajemen Karyawan - Hanya Admin
    Route::middleware('admin')->group(function () {
        Route::get('/karyawan', [EmployeeController::class, 'index'])->name('employee.index');
        Route::get('/karyawan/tambah', [EmployeeController::class, 'create'])->name('employee.create');
        Route::post('/karyawan', [EmployeeController::class, 'store'])->name('employee.store');
        Route::get('/karyawan/{user}/edit', [EmployeeController::class, 'edit'])->name('employee.edit');
        Route::put('/karyawan/{user}', [EmployeeController::class, 'update'])->name('employee.update');
        Route::delete('/karyawan/{user}', [EmployeeController::class, 'destroy'])->name('employee.destroy');
        
        Route::get('/audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('audit_logs.index');
        
        // Server Panel - Restricted to username 'admin'
        Route::get('/server-panel', [ServerPanelController::class, 'index'])->name('server-panel.index');
        Route::get('/api/server-metrics', [ServerPanelController::class, 'getMetrics'])->name('server-panel.api');
        
        // Backup & Restore
        Route::get('/backup', [\App\Http\Controllers\BackupController::class, 'index'])->name('backup.index');
        Route::post('/backup/create', [\App\Http\Controllers\BackupController::class, 'create'])->name('backup.create');
        Route::get('/backup/download/{fileName}', [\App\Http\Controllers\BackupController::class, 'download'])->name('backup.download');
        Route::delete('/backup/{fileName}', [\App\Http\Controllers\BackupController::class, 'delete'])->name('backup.delete');
        Route::post('/backup/restore', [\App\Http\Controllers\BackupController::class, 'restore'])->name('backup.restore');
    });


    // Booking Studio - Bisa diakses admin & employee
    Route::prefix('booking')->group(function () {
        Route::get('/', [BookingController::class, 'index'])->name('booking.index');
        Route::get('/create', [BookingController::class, 'create'])->name('booking.create');
        Route::post('/', [BookingController::class, 'store'])->name('booking.store');
        Route::get('/{booking}', [BookingController::class, 'show'])->name('booking.show');
        Route::get('/{booking}/edit', [BookingController::class, 'edit'])->name('booking.edit');
        Route::put('/{booking}', [BookingController::class, 'update'])->name('booking.update');
        Route::delete('/{booking}', [BookingController::class, 'destroy'])->name('booking.destroy');
        Route::post('/{booking}/mark-as-done', [BookingController::class, 'markAsDone'])->name('booking.markAsDone');
        Route::get('/{booking}/invoice', [BookingController::class, 'invoice'])->name('booking.invoice');
    });
});
