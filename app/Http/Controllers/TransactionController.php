<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    // 1. Halaman Utama Kasir (Menampilkan Produk)
    public function index()
    {
        // Cache produk yang tersedia untuk 5 menit (300 detik)
        // Sekarang mencakup semua produk agar "sinkron" dengan manajemen produk
        $products = cache()->remember('products.available', 300, function () {
            return Product::all();
        });
        
        // Get 15 most popular products based on transaction history
        $popularProducts = cache()->remember('products.popular', 300, function () {
            return Product::withCount(['transactionItems as total_sold' => function($query) {
                    $query->select(DB::raw('SUM(quantity)'));
                }])
                ->orderBy('total_sold', 'desc')
                ->take(15)
                ->get();
        });
        
        return view('transaction.index', compact('products', 'popularProducts'));
    }
    
    // Memberikan data JSON produk untuk update UI via Sync button
    public function getProductsJson(Request $request)
    {
        // Jika parameter force_refresh ada, clear cache dulu
        if ($request->has('refresh')) {
            cache()->forget('products.available');
            cache()->forget('products.popular');
        }
        
        $products = cache()->remember('products.available', 300, function () {
            return Product::all();
        });
        
        $popularProducts = cache()->remember('products.popular', 300, function () {
            return Product::withCount(['transactionItems as total_sold' => function($query) {
                    $query->select(DB::raw('SUM(quantity)'));
                }])
                ->orderBy('total_sold', 'desc')
                ->take(15)
                ->get();
        });
        
        return response()->json([
            'success' => true,
            'products' => $products,
            'popularProducts' => $popularProducts,
            'server_time' => now()->translatedFormat('d M Y H:i:s')
        ]);
    }


    // 2. Proses Checkout (Inti Backend Kasir)
    public function store(Request $request)
    {
        // Validasi data yang dikirim dari frontend
        $request->validate([
            'customer_type' => 'required|in:umum,agen1,agen2',
            'payment_method' => 'required|string',
            'cart' => 'required|array', // Array berisi id produk, qty, dan harga
            'cart.*.id' => 'required|exists:products,id',
            'cart.*.qty' => 'required|integer|min:1',
            'cart.*.price' => 'required|numeric|min:0', // Izinkan harga kustom dari frontend
            'down_payment' => 'nullable|numeric|min:0',
            'is_dp' => 'nullable|boolean',
        ]);

        try {
            // DB::transaction memastikan semua proses sukses. 
            // Jika stok gagal dikurangi, transaksi batal otomatis (Rollback).
            $transaction = DB::transaction(function () use ($request) {
                
                // Tentukan status transaksi berdasarkan DP
                $isDP = $request->has('is_dp') && $request->is_dp == '1';
                $downPayment = $isDP ? ($request->down_payment ?? 0) : 0;
                $status = $isDP ? 'pending' : 'paid';
                
                // A. Buat Header Transaksi
                $trx = Transaction::create([
                    'invoice_id' => 'INV-' . date('YmdHis') . '-' . rand(100, 999),
                    'user_id' => Auth::id() ?? 1, // Fallback ke ID 1 jika testing tanpa login
                    'customer_name' => $request->customer_name ?? 'Pelanggan Umum',
                    'customer_phone' => $request->customer_phone ?? null,
                    'customer_type' => $request->customer_type,
                    'payment_method' => $request->payment_method,
                    'total_amount' => 0, // Nanti diupdate setelah hitung item
                    'down_payment' => $downPayment,
                    'remaining_amount' => 0, // Nanti diupdate setelah hitung item
                    'status' => $status,
                ]);

                $grandTotal = 0;
                $totalMargin = 0;

                // B. Proses Setiap Item di Keranjang
                foreach ($request->cart as $itemData) {
                    // LockForUpdate mencegah race condition (rebutan stok antar kasir)
                    $product = Product::lockForUpdate()->find($itemData['id']);

                    // Cek ketersediaan stok (kecuali untuk produk jasa)
                    if (!$product->isServiceProduct() && $product->stock < $itemData['qty']) {
                        throw new \Exception("Stok barang '{$product->name}' tidak mencukupi. Sisa: {$product->stock}");
                    }

                    // Gunakan harga kustom dari frontend. Jika tidak ada, baru ambil harga default.
                    $price = $itemData['price'] ?? $product->getPriceForCustomer($request->customer_type);
                    $subtotal = $price * $itemData['qty'];
                    $itemMargin = ($price - ($product->buy_price ?? 0)) * $itemData['qty'];

                    // Simpan Detail Item
                    TransactionItem::create([
                        'transaction_id' => $trx->id,
                        'product_id' => $product->id,
                        'quantity' => $itemData['qty'],
                        'price_at_transaction' => $price, // Harga dikunci saat transaksi terjadi
                        'subtotal' => $subtotal,
                        'margin' => $itemMargin,
                    ]);

                    // Kurangi Stok Fisik (kecuali untuk produk jasa)
                    if (!$product->isServiceProduct()) {
                        $product->decrement('stock', $itemData['qty']);
                    }

                    $grandTotal += $subtotal;
                    $totalMargin += $itemMargin;
                }

                // C. Update Total Akhir dan sisa pembayaran
                $remainingAmount = $grandTotal - $downPayment;
                $trx->update([
                    'total_amount' => $grandTotal,
                    'remaining_amount' => $remainingAmount,
                    'margin' => $totalMargin,
                ]);

                return $trx;
            });

            \App\Models\AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $transaction->status === 'paid' ? 'transaksi masuk' : 'transaksi masuk (DP)',
                'details' => $transaction->toArray()
            ]);

            // Clear product caches after transaction (stock changed and popularity updated)
            cache()->forget('products.available');
            cache()->forget('products.popular');

            // Jika AJAX, kembalikan URL redirect dalam format JSON
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'transaction_id' => $transaction->id,
                    'redirect_url' => route('transaction.receipt.iframe', $transaction->id)
                ]);
            }

            // Redirect ke halaman struk dalam iframe
            return redirect()->route('transaction.receipt.iframe', $transaction->id);

        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);
            }
            // Jika error (misal stok habis), kembali ke kasir dengan pesan
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    // 3. Menampilkan Struk (Menggunakan view receipt.blade.php Anda)
    public function receipt(Transaction $transaction)
    {
        // Load relasi user dan items->product agar struk lengkap
        $transaction->load(['user', 'items.product']);
        return view('transaction.receipt', compact('transaction'));
    }
    
    // 4. Menampilkan Struk dalam Iframe
    public function receiptIframe(Transaction $transaction)
    {
        // Load relasi user dan items->product agar struk lengkap
        $transaction->load(['user', 'items.product']);
        return view('transaction.receipt_iframe', compact('transaction'));
    }

    // 4. Menampilkan Riwayat Transaksi
    public function history(Request $request)
    {
        $user = Auth::user();
        
        // Ambil semua transaksi (semua orang dapat melihat riwayat transaksi)
        $query = Transaction::with(['user', 'items']);
        
        // Set default 1-week filter jika tidak ada tanggal yang dipilih
        if (!$request->has('start_date') && !$request->has('end_date')) {
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime('-1 week'));
            
            // Set default dates in request untuk ditampilkan di form
            $request->merge([
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
            $query->whereBetween('created_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ]);
        } else {
            // Filter berdasarkan tanggal yang dipilih user
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('created_at', [
                    $request->start_date . ' 00:00:00',
                    $request->end_date . ' 23:59:59'
                ]);
            } elseif ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            } elseif ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }
        }
        
        // Filter berdasarkan status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        // Filter berdasarkan tipe pelanggan
        if ($request->has('customer_type') && $request->customer_type !== 'all') {
            $query->where('customer_type', $request->customer_type);
        }
        
        $transactions = $query->latest()->paginate(20)->withQueryString();
        
        // Untuk perhitungan revenue, kita perlu menghitung total dari transaksi yang tidak dibatalkan
        // Jika status "pending", input DP sebagai pemasukan
        // Jika status "lunas", input total harga sebagai pemasukan
        $revenueQuery = clone $query;
        $totalRevenue = $revenueQuery->where('status', '!=', 'cancelled')
            ->get()
            ->sum(function($transaction) {
                if ($transaction->status === 'pending') {
                    return $transaction->down_payment;
                } else {
                    return $transaction->total_amount;
                }
            });
        
        // Hitung rata-rata transaksi
        $validTransactions = $revenueQuery->where('status', '!=', 'cancelled')->get();
        $averageTransaction = $validTransactions->count() > 0 
            ? $validTransactions->avg(function($transaction) {
                if ($transaction->status === 'pending') {
                    return $transaction->down_payment;
                } else {
                    return $transaction->total_amount;
                }
            })
            : 0;
        
        // Hitung jumlah transaksi pending
        $pendingTransactionsCount = $query->clone()->where('status', 'pending')->count();
        
        // ==================== KALKULASI OMSEET ====================
        
        // 1. Omset 7 Hari Terakhir (hari ini sampai 7 hari sebelumnya)
        $weeklyEndDate = date('Y-m-d');
        $weeklyStartDate = date('Y-m-d', strtotime('-7 days'));
        
        $weeklyQuery = Transaction::query();
        if ($user->role !== 'admin') {
            $weeklyQuery->where('user_id', $user->id);
        }
        
        $weeklyTransactions = $weeklyQuery->whereBetween('created_at', [
            $weeklyStartDate . ' 00:00:00',
            $weeklyEndDate . ' 23:59:59'
        ])->where('status', '!=', 'cancelled')->get();
        
        $weeklyRevenue = $weeklyTransactions->sum(function($transaction) {
            if ($transaction->status === 'pending') {
                return $transaction->down_payment;
            } else {
                return $transaction->total_amount;
            }
        });
        
        $weeklyTransactionCount = $weeklyTransactions->count();
        $weeklyAverage = $weeklyTransactionCount > 0 ? $weeklyRevenue / $weeklyTransactionCount : 0;
        $weeklyMargin = $weeklyTransactions->sum('margin');
        
        // 2. Omset Bulan Ini (tanggal 1 sampai hari ini)
        $monthlyStartDate = date('Y-m-01'); // Tanggal 1 bulan ini
        $monthlyEndDate = date('Y-m-d'); // Hari ini
        
        $monthlyQuery = Transaction::query();
        if ($user->role !== 'admin') {
            $monthlyQuery->where('user_id', $user->id);
        }
        
        $monthlyTransactions = $monthlyQuery->whereBetween('created_at', [
            $monthlyStartDate . ' 00:00:00',
            $monthlyEndDate . ' 23:59:59'
        ])->where('status', '!=', 'cancelled')->get();
        
        $monthlyRevenue = $monthlyTransactions->sum(function($transaction) {
            if ($transaction->status === 'pending') {
                return $transaction->down_payment;
            } else {
                return $transaction->total_amount;
            }
        });
        
        $monthlyTransactionCount = $monthlyTransactions->count();
        $monthlyAverage = $monthlyTransactionCount > 0 ? $monthlyRevenue / $monthlyTransactionCount : 0;
        $monthlyMargin = $monthlyTransactions->sum('margin');
        
        // 3. Omset 30 Hari Terakhir (hari ini sampai 30 hari kebelakang)
        $last30DaysEndDate = date('Y-m-d');
        $last30DaysStartDate = date('Y-m-d', strtotime('-30 days'));
        
        $last30DaysQuery = Transaction::query();
        if ($user->role !== 'admin') {
            $last30DaysQuery->where('user_id', $user->id);
        }
        
        $last30DaysTransactions = $last30DaysQuery->whereBetween('created_at', [
            $last30DaysStartDate . ' 00:00:00',
            $last30DaysEndDate . ' 23:59:59'
        ])->where('status', '!=', 'cancelled')->get();
        
        $last30DaysRevenue = $last30DaysTransactions->sum(function($transaction) {
            if ($transaction->status === 'pending') {
                return $transaction->down_payment;
            } else {
                return $transaction->total_amount;
            }
        });
        
        $last30DaysTransactionCount = $last30DaysTransactions->count();
        $last30DaysAverage = $last30DaysTransactionCount > 0 ? $last30DaysRevenue / $last30DaysTransactionCount : 0;
        $last30DaysMargin = $last30DaysTransactions->sum('margin');
        
        // ==================== BREAKDOWN REVENUE ====================
        
        // Breakdown berdasarkan status
        $paidRevenue = $query->clone()->where('status', 'paid')->sum('total_amount');
        $pendingRevenue = $query->clone()->where('status', 'pending')->sum('down_payment');
        $downPaymentRevenue = $query->clone()->where('status', 'pending')->sum('down_payment');
        
        // Calculate DP profit percentage (current month vs last month)
        $currentMonth = date('Y-m');
        $lastMonth = date('Y-m', strtotime('-1 month'));
        
        // Get current month down payments
        $currentMonthDPQuery = Transaction::query();
        if ($user->role !== 'admin') {
            $currentMonthDPQuery->where('user_id', $user->id);
        }
        
        $currentMonthDP = $currentMonthDPQuery->where('status', 'pending')
            ->whereYear('created_at', date('Y'))
            ->whereMonth('created_at', date('m'))
            ->sum('down_payment');
        
        // Get last month down payments
        $lastMonthDPQuery = Transaction::query();
        if ($user->role !== 'admin') {
            $lastMonthDPQuery->where('user_id', $user->id);
        }
        
        $lastMonthDP = $lastMonthDPQuery->where('status', 'pending')
            ->whereYear('created_at', date('Y', strtotime('-1 month')))
            ->whereMonth('created_at', date('m', strtotime('-1 month')))
            ->sum('down_payment');
        
        // Calculate profit percentage
        $dpProfitPercentage = 0;
        if ($lastMonthDP > 0) {
            $dpProfitPercentage = (($currentMonthDP - $lastMonthDP) / $lastMonthDP) * 100;
        } elseif ($currentMonthDP > 0 && $lastMonthDP == 0) {
            // If last month was 0 and current month has DP, show 100% growth
            $dpProfitPercentage = 100;
        }
        
        // Breakdown berdasarkan tipe pelanggan
        $generalRevenue = $query->clone()->where('customer_type', 'umum')
            ->where('status', '!=', 'cancelled')
            ->get()
            ->sum(function($transaction) {
                if ($transaction->status === 'pending') {
                    return $transaction->down_payment;
                } else {
                    return $transaction->total_amount;
                }
            });
        
        $agent1Revenue = $query->clone()->where('customer_type', 'agen1')
            ->where('status', '!=', 'cancelled')
            ->get()
            ->sum(function($transaction) {
                if ($transaction->status === 'pending') {
                    return $transaction->down_payment;
                } else {
                    return $transaction->total_amount;
                }
            });
        
        $agent2Revenue = $query->clone()->where('customer_type', 'agen2')
            ->where('status', '!=', 'cancelled')
            ->get()
            ->sum(function($transaction) {
                if ($transaction->status === 'pending') {
                    return $transaction->down_payment;
                } else {
                    return $transaction->total_amount;
                }
            });
        
        // Breakdown berdasarkan metode pembayaran
        $cashRevenue = $query->clone()->where('payment_method', 'Cash')
            ->where('status', '!=', 'cancelled')
            ->get()
            ->sum(function($transaction) {
                if ($transaction->status === 'pending') {
                    return $transaction->down_payment;
                } else {
                    return $transaction->total_amount;
                }
            });
        
        $transferRevenue = $query->clone()->where('payment_method', 'Transfer')
            ->where('status', '!=', 'cancelled')
            ->get()
            ->sum(function($transaction) {
                if ($transaction->status === 'pending') {
                    return $transaction->down_payment;
                } else {
                    return $transaction->total_amount;
                }
            });
        
        $qrisRevenue = $query->clone()->where('payment_method', 'QRIS')
            ->where('status', '!=', 'cancelled')
            ->get()
            ->sum(function($transaction) {
                if ($transaction->status === 'pending') {
                    return $transaction->down_payment;
                } else {
                    return $transaction->total_amount;
                }
            });
        
        // Format tanggal untuk tampilan
        $weeklyStartDateFormatted = date('d/m/Y', strtotime($weeklyStartDate));
        $weeklyEndDateFormatted = date('d/m/Y', strtotime($weeklyEndDate));
        $monthlyPeriod = date('d/m/Y', strtotime($monthlyStartDate)) . ' - ' . date('d/m/Y', strtotime($monthlyEndDate));
        $last30DaysStartDateFormatted = date('d/m/Y', strtotime($last30DaysStartDate));
        $last30DaysEndDateFormatted = date('d/m/Y', strtotime($last30DaysEndDate));
        
        // Kirim data tambahan ke view
        return view('transaction.history', compact(
            'transactions', 
            'totalRevenue', 
            'averageTransaction',
            'pendingTransactionsCount',
            // Omset data
            'weeklyRevenue',
            'weeklyTransactionCount',
            'weeklyAverage',
            'weeklyMargin',
            'weeklyStartDate',
            'weeklyEndDate',
            'weeklyStartDateFormatted',
            'weeklyEndDateFormatted',
            // Monthly data
            'monthlyRevenue',
            'monthlyTransactionCount',
            'monthlyAverage',
            'monthlyPeriod',
            'monthlyMargin',
            // Last 30 days data
            'last30DaysRevenue',
            'last30DaysTransactionCount',
            'last30DaysAverage',
            'last30DaysStartDateFormatted',
            'last30DaysEndDateFormatted',
            'last30DaysMargin',
            // Breakdown data
            'paidRevenue',
            'pendingRevenue',
            'downPaymentRevenue',
            'dpProfitPercentage',
            'generalRevenue',
            'agent1Revenue',
            'agent2Revenue',
            'cashRevenue',
            'transferRevenue',
            'qrisRevenue'
        ));
    }
    
    // 5. Export Laporan Keuangan 1 Bulan
    public function export(Request $request)
    {
        $user = Auth::user();
        
        $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $startOfMonth = \Carbon\Carbon::parse($request->month)->startOfMonth();
        $endOfMonth = \Carbon\Carbon::parse($request->month)->endOfMonth();

        if ($user->role !== 'admin') {
            // For employee, export a simple CSV of their own transactions for the month (no margins)
            $transactions = Transaction::with('user')
                ->where('user_id', $user->id)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->latest()
                ->get();
                
            $csv = "Invoice ID,Tanggal,Kasir,Pelanggan,Tipe Pelanggan,Metode Bayar,Total,Status\n";
            foreach ($transactions as $transaction) {
                $csv .= sprintf(
                    '"%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                    $transaction->invoice_id,
                    $transaction->created_at->format('d/m/Y H:i'),
                    $transaction->user->name ?? 'N/A',
                    $transaction->customer_name ?? 'Pelanggan Umum',
                    $transaction->customer_type,
                    $transaction->payment_method,
                    number_format($transaction->total_amount, 0, ',', '.'),
                    $transaction->status === 'paid' ? 'Lunas' : ($transaction->status === 'pending' ? 'Pending' : 'Dibatalkan')
                );
            }
            
            $filename = 'transaksi_saya_' . $startOfMonth->format('M_Y') . '.csv';
            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        }

        // For admin: export a complete 1-month financial report
        $transactions = Transaction::whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->with('user')
            ->get();

        // Statistik Performa & Total Revenue
        $totalSuccess = $transactions->where('status', 'paid')->count();
        $totalPending = $transactions->where('status', 'pending')->count();
        $totalCancelled = $transactions->where('status', 'cancelled')->count();
        
        $totalRevenueForAll = $transactions->where('status', '!=', 'cancelled')
            ->sum(function($t) { return $t->status === 'pending' ? $t->down_payment : $t->total_amount; });

        // Pendapatan per Kategori
        $categoryResults = DB::table('transaction_items')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->whereBetween('transactions.created_at', [$startOfMonth, $endOfMonth])
            ->where('transactions.status', '!=', 'cancelled')
            ->select(
                'categories.name as category_name',
                DB::raw('SUM(transaction_items.quantity) as total_qty'),
                DB::raw('SUM(transaction_items.subtotal) as total_revenue'),
                DB::raw('SUM(transaction_items.margin) as total_margin')
            )
            ->groupBy('categories.id', 'categories.name')
            ->get();

        // Generate CSV Content
        $filename = 'Laporan_Keuangan_Detil_' . $startOfMonth->format('M_Y') . '.csv';
        $handle = fopen('php://memory', 'r+');
        
        fputcsv($handle, ['LAPORAN KEUANGAN DETAIL 1 BULAN']);
        fputcsv($handle, ['Periode', $startOfMonth->format('d/m/Y') . ' - ' . $endOfMonth->format('d/m/Y')]);
        fputcsv($handle, ['Dicetak Oleh', $user->name]);
        fputcsv($handle, ['Tanggal Cetak', date('d/m/Y H:i')]);
        fputcsv($handle, ['Total Omset Periode', 'Rp ' . number_format($totalRevenueForAll, 0, ',', '.')]);
        fputcsv($handle, []);

        // Section: Ringkasan Transaksi
        fputcsv($handle, ['RINGKASAN STATUS TRANSAKSI']);
        fputcsv($handle, ['Status', 'Jumlah']);
        fputcsv($handle, ['Total Berhasil (Lunas)', $totalSuccess]);
        fputcsv($handle, ['Total Pending (DP)', $totalPending]);
        fputcsv($handle, ['Total Dibatalkan', $totalCancelled]);
        fputcsv($handle, []);

        // Section: Performa Karyawan
        fputcsv($handle, ['PERFORMA KARYAWAN']);
        fputcsv($handle, ['Nama Karyawan', 'Role', 'Jumlah Transaksi', 'Total Pendapatan', 'Kontribusi (%)']);
        
        $employeeGroups = $transactions->where('status', '!=', 'cancelled')->groupBy('user_id');
        $employeeStatsArray = [];
        
        foreach ($employeeGroups as $userId => $groupDetails) {
            $firstTrans = $groupDetails->first();
            $userName = $firstTrans->user ? $firstTrans->user->name : 'System/Deleted';
            $userRole = $firstTrans->user ? ucfirst($firstTrans->user->role) : 'System';
            
            $totalIncome = $groupDetails->sum(function($t) {
                return $t->status === 'pending' ? $t->down_payment : $t->total_amount;
            });
            
            $employeeStatsArray[] = [
                'name' => $userName,
                'role' => $userRole,
                'count' => $groupDetails->count(),
                'income' => $totalIncome,
            ];
        }
        
        usort($employeeStatsArray, function($a, $b) {
            return $b['income'] <=> $a['income'];
        });

        foreach ($employeeStatsArray as $emp) {
            $perc = $totalRevenueForAll > 0 ? ($emp['income'] / $totalRevenueForAll) * 100 : 0;
            fputcsv($handle, [
                $emp['name'], 
                $emp['role'], 
                $emp['count'], 
                number_format($emp['income'], 0, ',', '.'), 
                number_format($perc, 2) . '%'
            ]);
        }
        fputcsv($handle, []);

        // Section: Pendapatan per Kategori
        fputcsv($handle, ['PENDAPATAN PER KATEGORI']);
        fputcsv($handle, ['Nama Kategori', 'Jumlah Terjual', 'Total Omset', 'Total Margin (Pemasukan)', 'Kontribusi (%)']);
        foreach ($categoryResults as $cat) {
            $perc = $totalRevenueForAll > 0 ? ($cat->total_revenue / $totalRevenueForAll) * 100 : 0;
            fputcsv($handle, [$cat->category_name, $cat->total_qty, number_format($cat->total_revenue, 0, ',', '.'), number_format($cat->total_margin ?? 0, 0, ',', '.'), number_format($perc, 2) . '%']);
        }
        fputcsv($handle, []);

        // Section: Pendapatan per Metode Pembayaran
        fputcsv($handle, ['PENDAPATAN PER METODE PEMBAYARAN']);
        fputcsv($handle, ['Metode', 'Jumlah Transaksi', 'Total Pemasukan', 'Kontribusi (%)']);
        $pms = $transactions->where('status', '!=', 'cancelled')->groupBy('payment_method');
        foreach ($pms as $method => $group) {
            $total = $group->sum(function($t) { return $t->status === 'pending' ? $t->down_payment : $t->total_amount; });
            $perc = $totalRevenueForAll > 0 ? ($total / $totalRevenueForAll) * 100 : 0;
            fputcsv($handle, [$method, $group->count(), number_format($total, 0, ',', '.'), number_format($perc, 2) . '%']);
        }
        fputcsv($handle, []);

        // Section: Pendapatan per Tipe Pelanggan
        fputcsv($handle, ['PENDAPATAN PER TIPE PELANGGAN']);
        fputcsv($handle, ['Tipe Pelanggan', 'Jumlah Transaksi', 'Total Pemasukan', 'Kontribusi (%)']);
        $ctype = $transactions->where('status', '!=', 'cancelled')->groupBy('customer_type');
        foreach ($ctype as $type => $group) {
            $total = $group->sum(function($t) { return $t->status === 'pending' ? $t->down_payment : $t->total_amount; });
            $perc = $totalRevenueForAll > 0 ? ($total / $totalRevenueForAll) * 100 : 0;
            fputcsv($handle, [ucfirst($type), $group->count(), number_format($total, 0, ',', '.'), number_format($perc, 2) . '%']);
        }
        fputcsv($handle, []);

        // Section: Daftar Transaksi Detail
        fputcsv($handle, ['DAFTAR TRANSAKSI DETAIL']);
        fputcsv($handle, ['ID Invoice', 'Tanggal', 'Kasir', 'Pelanggan', 'Metode', 'Total', 'DP', 'Margin', 'Status', '% Omset']);
        foreach ($transactions as $t) {
            $rev = $t->status === 'cancelled' ? 0 : ($t->status === 'pending' ? $t->down_payment : $t->total_amount);
            $perc = $totalRevenueForAll > 0 ? ($rev / $totalRevenueForAll) * 100 : 0;
            fputcsv($handle, [
                $t->invoice_id,
                $t->created_at->format('d/m/Y H:i'),
                $t->user->name ?? 'N/A',
                $t->customer_name,
                $t->payment_method,
                number_format($t->total_amount, 0, ',', '.'),
                number_format($t->down_payment, 0, ',', '.'),
                number_format($t->margin, 0, ',', '.'),
                $t->status,
                number_format($perc, 2) . '%'
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    // 6. Batalkan Transaksi
    public function cancel(Request $request, Transaction $transaction)
    {
        $user = Auth::user();
        
        // Admin bisa membatalkan semua transaksi
        // Kasir hanya bisa membatalkan transaksi yang mereka layani sendiri
        if ($user->role !== 'admin' && $transaction->user_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda hanya dapat membatalkan transaksi yang Anda layani sendiri.'
            ], 403);
        }

        // Hanya transaksi dengan status 'paid' atau 'pending' yang bisa dibatalkan
        if (!in_array($transaction->status, ['paid', 'pending'])) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya transaksi dengan status LUNAS atau PENDING yang dapat dibatalkan.'
            ], 400);
        }

        try {
            DB::transaction(function () use ($transaction) {
                $transaction->update(['status' => 'cancelled']);
                
                \App\Models\AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'transaksi dibatalkan',
                    'details' => $transaction->toArray()
                ]);
                
                // Kembalikan stok produk yang dibeli (kecuali untuk produk jasa)
                foreach ($transaction->items as $item) {
                    if ($item->product && !$item->product->isServiceProduct()) {
                        $item->product->increment('stock', $item->quantity);
                    }
                }
            });

            // Clear product caches after stock update
            cache()->forget('products.available');
            cache()->forget('products.popular');

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil dibatalkan dan stok produk telah dikembalikan.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan transaksi: ' . $e->getMessage()
            ], 500);
        }
    }

    // 7. Tandai Transaksi sebagai Lunas
    public function markAsPaid(Request $request, Transaction $transaction)
    {
        $user = Auth::user();
        
        // Admin bisa menandai semua transaksi sebagai lunas
        // Kasir hanya bisa menandai transaksi yang mereka layani sendiri
        if ($user->role !== 'admin' && $transaction->user_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda hanya dapat menandai transaksi yang Anda layani sendiri sebagai lunas.'
            ], 403);
        }

        // Hanya transaksi dengan status 'pending' yang bisa ditandai sebagai lunas
        if ($transaction->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya transaksi dengan status PENDING yang dapat ditandai sebagai lunas.'
            ], 400);
        }

        try {
            DB::transaction(function () use ($transaction) {
                // Update status transaksi menjadi 'paid' dan sisa pembayaran menjadi 0
                $transaction->update([
                    'status' => 'paid',
                    'remaining_amount' => 0,
                ]);

                \App\Models\AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'transaksi lunas',
                    'details' => $transaction->toArray()
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil ditandai sebagai LUNAS!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menandai transaksi sebagai lunas: ' . $e->getMessage()
            ], 500);
        }
    }

    // 7.5 Redo Transaksi yang Dibatalkan
    public function redoTransaction(Request $request, Transaction $transaction)
    {
        $user = Auth::user();
        
        if ($user->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Hanya Admin yang dapat melakukan aksi Redo.'], 403);
        }

        if ($transaction->status !== 'cancelled') {
            return response()->json(['success' => false, 'message' => 'Hanya transaksi dibatalkan yang dapat dilakukan Redo.'], 400);
        }

        // Check if ANY item is a booking item (product_id null)
        if ($transaction->items->contains('product_id', null)) {
            return response()->json(['success' => false, 'message' => 'Transaksi dari booking studio tidak dapat di-redo.'], 400);
        }

        $transaction->load('items.product.category');

        try {
            DB::beginTransaction();

            foreach ($transaction->items as $item) {
                $product = $item->product;
                
                // Jika barang sudah dihapus dari database
                if (!$product) {
                    throw new \Exception("tidak dapat mengembalikan (barang tidak tersedia). silahkan buat pesanan baru");
                }

                // Cek stok untuk produk fisik
                if (!$product->isServiceProduct()) {
                    if ($product->stock < $item->quantity) {
                        throw new \Exception("barang kekurangan stok ({$product->name})");
                    }
                    $product->decrement('stock', $item->quantity);
                }
            }

            // Tentukan status kembalian: jika masih ada sisa bayar > 0, set ke pending
            $newStatus = ($transaction->remaining_amount > 0) ? 'pending' : 'paid';
            $transaction->update(['status' => $newStatus]);

            \App\Models\AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'transaksi redo',
                'details' => $transaction->fresh()->toArray()
            ]);

            // Clear product caches after stock update
            cache()->forget('products.available');
            cache()->forget('products.popular');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil di-redo!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    // 8. Export Laporan Keuangan 3 Bulan
    public function exportFinancialReport(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return abort(403, 'Anda tidak memiliki hak akses untuk fitur ini.');
        }

        $request->validate([
            'end_month' => 'required|date_format:Y-m',
        ]);

        $user = Auth::user();
        $endOfMonth = \Carbon\Carbon::parse($request->end_month)->endOfMonth();
        $startOfPeriod = \Carbon\Carbon::parse($request->end_month)->subMonths(2)->startOfMonth();

        // 1. Ambil data transaksi periode tersebut
        $query = Transaction::whereBetween('created_at', [$startOfPeriod, $endOfMonth]);
        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }
        $transactions = $query->with('user')->get();

        // 2. Statistik Performa & Total Revenue
        $totalSuccess = $transactions->where('status', 'paid')->count();
        $totalPending = $transactions->where('status', 'pending')->count();
        $totalCancelled = $transactions->where('status', 'cancelled')->count();
        
        $totalRevenueForAll = $transactions->where('status', '!=', 'cancelled')
            ->sum(function($t) { return $t->status === 'pending' ? $t->down_payment : $t->total_amount; });

        // 3. Perbandingan Omset Bulanan (3 Bulan)
        $monthlyStats = [];
        $tempPeriod = clone $startOfPeriod;
        while ($tempPeriod <= $endOfMonth) {
            $monthKey = $tempPeriod->format('Y-m');
            $monthLabel = $tempPeriod->format('F Y');
            $monthTransactions = $transactions->where('status', '!=', 'cancelled')
                ->filter(function($t) use ($monthKey) {
                    return $t->created_at->format('Y-m') === $monthKey;
                });
            
            $revenue = $monthTransactions->sum(function($t) { 
                return $t->status === 'pending' ? $t->down_payment : $t->total_amount; 
            });
            
            $monthlyStats[] = [
                'label' => $monthLabel,
                'revenue' => $revenue,
                'count' => $monthTransactions->count()
            ];
            $tempPeriod->addMonth();
        }

        // 4. Pendapatan per Kategori
        $categoryStats = DB::table('transaction_items')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->whereBetween('transactions.created_at', [$startOfPeriod, $endOfMonth])
            ->where('transactions.status', '!=', 'cancelled');
            
        if ($user->role !== 'admin') {
            $categoryStats->where('transactions.user_id', $user->id);
        }

        $categoryResults = $categoryStats->select(
                'categories.name as category_name',
                DB::raw('SUM(transaction_items.quantity) as total_qty'),
                DB::raw('SUM(transaction_items.subtotal) as total_revenue'),
                DB::raw('SUM(transaction_items.margin) as total_margin')
            )
            ->groupBy('categories.id', 'categories.name')
            ->get();

        // 5. Generate CSV Content
        $filename = 'Laporan_Keuangan_Detil_' . $startOfPeriod->format('M_Y') . '_sd_' . $endOfMonth->format('M_Y') . '.csv';
        $handle = fopen('php://memory', 'r+');
        
        fputcsv($handle, ['LAPORAN KEUANGAN DETAIL 3 BULAN']);
        fputcsv($handle, ['Periode', $startOfPeriod->format('d/m/Y') . ' - ' . $endOfMonth->format('d/m/Y')]);
        fputcsv($handle, ['Dicetak Oleh', $user->name]);
        fputcsv($handle, ['Tanggal Cetak', date('d/m/Y H:i')]);
        fputcsv($handle, ['Total Omset Periode', 'Rp ' . number_format($totalRevenueForAll, 0, ',', '.')]);
        fputcsv($handle, []);

        // Section: Ringkasan Transaksi
        fputcsv($handle, ['RINGKASAN STATUS TRANSAKSI']);
        fputcsv($handle, ['Status', 'Jumlah']);
        fputcsv($handle, ['Total Berhasil (Lunas)', $totalSuccess]);
        fputcsv($handle, ['Total Pending (DP)', $totalPending]);
        fputcsv($handle, ['Total Dibatalkan', $totalCancelled]);
        fputcsv($handle, []);

        // Section: Perbandingan Bulanan
        fputcsv($handle, ['PERBANDINGAN OMSET BULANAN']);
        fputcsv($handle, ['Bulan', 'Jumlah Transaksi', 'Total Omset', 'Kontribusi (%)']);
        foreach ($monthlyStats as $stat) {
            $perc = $totalRevenueForAll > 0 ? ($stat['revenue'] / $totalRevenueForAll) * 100 : 0;
            fputcsv($handle, [$stat['label'], $stat['count'], number_format($stat['revenue'], 0, ',', '.'), number_format($perc, 2) . '%']);
        }
        fputcsv($handle, []);

        // Section: Performa Karyawan
        fputcsv($handle, ['PERFORMA KARYAWAN']);
        fputcsv($handle, ['Nama Karyawan', 'Role', 'Jumlah Transaksi', 'Total Pendapatan', 'Kontribusi (%)']);
        
        $employeeGroups = $transactions->where('status', '!=', 'cancelled')->groupBy('user_id');
        $employeeStatsArray = [];
        
        foreach ($employeeGroups as $userId => $groupDetails) {
            $firstTrans = $groupDetails->first();
            $userName = $firstTrans->user ? $firstTrans->user->name : 'System/Deleted';
            $userRole = $firstTrans->user ? ucfirst($firstTrans->user->role) : 'System';
            
            $totalIncome = $groupDetails->sum(function($t) {
                return $t->status === 'pending' ? $t->down_payment : $t->total_amount;
            });
            
            $employeeStatsArray[] = [
                'name' => $userName,
                'role' => $userRole,
                'count' => $groupDetails->count(),
                'income' => $totalIncome,
            ];
        }
        
        // Sort by income descending
        usort($employeeStatsArray, function($a, $b) {
            return $b['income'] <=> $a['income'];
        });

        foreach ($employeeStatsArray as $emp) {
            $perc = $totalRevenueForAll > 0 ? ($emp['income'] / $totalRevenueForAll) * 100 : 0;
            fputcsv($handle, [
                $emp['name'], 
                $emp['role'], 
                $emp['count'], 
                number_format($emp['income'], 0, ',', '.'), 
                number_format($perc, 2) . '%'
            ]);
        }
        fputcsv($handle, []);

        // Section: Pendapatan per Kategori
        fputcsv($handle, ['PENDAPATAN PER KATEGORI']);
        fputcsv($handle, ['Nama Kategori', 'Jumlah Terjual', 'Total Omset', 'Total Margin (Pemasukan)', 'Kontribusi (%)']);
        foreach ($categoryResults as $cat) {
            $perc = $totalRevenueForAll > 0 ? ($cat->total_revenue / $totalRevenueForAll) * 100 : 0;
            fputcsv($handle, [$cat->category_name, $cat->total_qty, number_format($cat->total_revenue, 0, ',', '.'), number_format($cat->total_margin ?? 0, 0, ',', '.'), number_format($perc, 2) . '%']);
        }
        fputcsv($handle, []);

        // Section: Pendapatan per Metode Pembayaran
        fputcsv($handle, ['PENDAPATAN PER METODE PEMBAYARAN']);
        fputcsv($handle, ['Metode', 'Jumlah Transaksi', 'Total Pemasukan', 'Kontribusi (%)']);
        $pms = $transactions->where('status', '!=', 'cancelled')->groupBy('payment_method');
        foreach ($pms as $method => $group) {
            $total = $group->sum(function($t) { return $t->status === 'pending' ? $t->down_payment : $t->total_amount; });
            $perc = $totalRevenueForAll > 0 ? ($total / $totalRevenueForAll) * 100 : 0;
            fputcsv($handle, [$method, $group->count(), number_format($total, 0, ',', '.'), number_format($perc, 2) . '%']);
        }
        fputcsv($handle, []);

        // Section: Pendapatan per Tipe Pelanggan
        fputcsv($handle, ['PENDAPATAN PER TIPE PELANGGAN']);
        fputcsv($handle, ['Tipe Pelanggan', 'Jumlah Transaksi', 'Total Pemasukan', 'Kontribusi (%)']);
        $ctype = $transactions->where('status', '!=', 'cancelled')->groupBy('customer_type');
        foreach ($ctype as $type => $group) {
            $total = $group->sum(function($t) { return $t->status === 'pending' ? $t->down_payment : $t->total_amount; });
            $perc = $totalRevenueForAll > 0 ? ($total / $totalRevenueForAll) * 100 : 0;
            fputcsv($handle, [ucfirst($type), $group->count(), number_format($total, 0, ',', '.'), number_format($perc, 2) . '%']);
        }
        fputcsv($handle, []);

        // Section: Daftar Transaksi Detail
        fputcsv($handle, ['DAFTAR TRANSAKSI 3 BULAN']);
        fputcsv($handle, ['ID Invoice', 'Tanggal', 'Kasir', 'Pelanggan', 'Metode', 'Total', 'DP', 'Margin', 'Status', '% Omset']);
        foreach ($transactions as $t) {
            $rev = $t->status === 'cancelled' ? 0 : ($t->status === 'pending' ? $t->down_payment : $t->total_amount);
            $perc = $totalRevenueForAll > 0 ? ($rev / $totalRevenueForAll) * 100 : 0;
            fputcsv($handle, [
                $t->invoice_id,
                $t->created_at->format('d/m/Y H:i'),
                $t->user->name ?? 'N/A',
                $t->customer_name,
                $t->payment_method,
                number_format($t->total_amount, 0, ',', '.'),
                number_format($t->down_payment, 0, ',', '.'),
                number_format($t->margin, 0, ',', '.'),
                $t->status,
                number_format($perc, 2) . '%'
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
