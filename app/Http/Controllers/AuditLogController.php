<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user');

        // Default: 3 hari kebelakang
        $startDate = $request->filled('start_date') ? $request->start_date : now()->subDays(3)->format('Y-m-d');
        $endDate = $request->filled('end_date') ? $request->end_date : now()->format('Y-m-d');

        $query->whereDate('created_at', '>=', $startDate)
              ->whereDate('created_at', '<=', $endDate);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        } elseif ($request->filled('type')) {
            $type = $request->type;
            if ($type === 'created') {
                $query->where(function($q) {
                    $q->where('action', 'like', '%added%')
                      ->orWhere('action', 'like', '%created%');
                });
            } elseif ($type === 'updated') {
                $query->where(function($q) {
                    $q->where('action', 'like', '%updated%')
                      ->orWhere('action', 'like', '%edited%')
                      ->orWhere('action', 'like', '%changed%')
                      ->orWhere('action', 'like', '%completed%');
                });
            } elseif ($type === 'deleted') {
                $query->where('action', 'like', '%deleted%');
            } elseif ($type === 'transaction') {
                $query->where('action', 'like', 'transaksi%');
            }
        }

        $logs = $query->latest()->paginate(50)->withQueryString();
        $users = \App\Models\User::all();
        $actions = AuditLog::distinct()->pluck('action')->sort();

        return view('audit_logs.index', compact('logs', 'users', 'actions'));
    }
}
