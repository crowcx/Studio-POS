<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    // Dashboard untuk Employee
    public function employeeDashboard()
    {
        $user = Auth::user();
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        // Jumlah order hari ini (hanya transaksi user ini, yang tidak dibatalkan)
        $todayOrders = Transaction::where('user_id', $user->id)
            ->whereDate('created_at', $today)
            ->where('status', '!=', 'cancelled')
            ->count();

        // Jumlah order minggu ini (hanya transaksi user ini, yang tidak dibatalkan)
        $weekOrders = Transaction::where('user_id', $user->id)
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->where('status', '!=', 'cancelled')
            ->count();

        // Omset minggu ini (hanya transaksi user ini)
        // Hitung total_amount jika lunas, dan down_payment jika pending
        $weekRevenue = Transaction::where('user_id', $user->id)
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->where('status', '!=', 'cancelled')
            ->get()
            ->sum(function($t) {
                return $t->status === 'pending' ? $t->down_payment : $t->total_amount;
            });

        // Produk dengan stok rendah (kurang dari 10)
        $lowStockProducts = Product::where('stock', '<', 10)
            ->orderBy('stock', 'asc')
            ->limit(5)
            ->get();

        // Transaksi terbaru (hanya user ini)
        $recentTransactions = Transaction::where('user_id', $user->id)
            ->with('user')
            ->latest()
            ->limit(5)
            ->get();

        return view('dashboard.employee', compact(
            'todayOrders',
            'weekOrders',
            'weekRevenue',
            'lowStockProducts',
            'recentTransactions'
        ));
    }

    // Dashboard untuk Admin
    public function adminDashboard()
    {
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        // Jumlah order hari ini (semua transaksi yang tidak dibatalkan)
        $todayOrders = Transaction::whereDate('created_at', $today)
            ->where('status', '!=', 'cancelled')
            ->count();

        // Jumlah order minggu ini (semua transaksi yang tidak dibatalkan)
        $weekOrders = Transaction::whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->where('status', '!=', 'cancelled')
            ->count();

        // Omset minggu ini (semua transaksi)
        $weekRevenue = Transaction::whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->where('status', '!=', 'cancelled')
            ->get()
            ->sum(function($t) {
                return $t->status === 'pending' ? $t->down_payment : $t->total_amount;
            });

        // Statistik karyawan (performance) - termasuk employee dan staff gudang
        $employeePerformance = User::whereIn('role', ['employee', 'staff gudang'])
            ->withCount(['transactions as today_transactions' => function ($query) use ($today) {
                $query->whereDate('created_at', $today)
                    ->where('status', '!=', 'cancelled');
            }])
            ->withCount(['transactions as week_transactions' => function ($query) use ($startOfWeek, $endOfWeek) {
                $query->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                    ->where('status', '!=', 'cancelled');
            }])
            ->get()
            ->map(function($user) use ($startOfWeek, $endOfWeek) {
                // Hitung revenue secara manual karena sum logic yang kompleks (DP vs Total)
                $user->week_revenue = Transaction::where('user_id', $user->id)
                    ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                    ->where('status', '!=', 'cancelled')
                    ->get()
                    ->sum(function($t) {
                        return $t->status === 'pending' ? $t->down_payment : $t->total_amount;
                    });
                return $user;
            });

        // Produk dengan stok rendah
        $lowStockProducts = Product::where('stock', '<', 10)
            ->orderBy('stock', 'asc')
            ->limit(10)
            ->get();

        // Transaksi terbaru (semua)
        $recentTransactions = Transaction::with('user')
            ->latest()
            ->limit(10)
            ->get();

        // Statistik penjualan per kategori (hanya transaksi yang tidak dibatalkan)
        $categorySales = DB::table('transaction_items')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->select(
                'categories.name as category_name',
                DB::raw('SUM(transaction_items.quantity) as total_quantity'),
                DB::raw('SUM(transaction_items.subtotal) as total_revenue')
            )
            ->whereBetween('transaction_items.created_at', [$startOfWeek, $endOfWeek])
            ->where('transactions.status', '!=', 'cancelled')
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('total_revenue', 'desc')
            ->get();

        return view('dashboard.admin', compact(
            'todayOrders',
            'weekOrders',
            'weekRevenue',
            'employeePerformance',
            'lowStockProducts',
            'recentTransactions',
            'categorySales'
        ));
    }
}