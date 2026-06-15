<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class EmployeeController extends Controller
{
    // Menampilkan daftar karyawan
    public function index()
    {
        $employees = User::where('id', '!=', auth()->id())->latest()->get();
        return view('employee.index', compact('employees'));
    }

    // Menampilkan form tambah karyawan
    public function create()
    {
        return view('employee.create');
    }

    // Menyimpan karyawan baru
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'pin' => ['required', 'string', 'min:4', 'max:6', 'confirmed'],
            'role' => ['required', 'string', 'in:admin,employee,staff gudang'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'pin' => Hash::make($request->pin),
            'role' => $request->role,
        ]);

        $logDetails = $user->toArray();
        $logDetails['pin'] = '******';

        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'user added',
            'details' => $logDetails
        ]);

        return redirect()->route('employee.index')
            ->with('success', 'Karyawan berhasil ditambahkan.');
    }

    // Menampilkan form edit karyawan
    public function edit(User $user)
    {
        // Pastikan tidak edit diri sendiri dari sini
        if ($user->id === auth()->id()) {
            abort(403, 'Gunakan menu profil untuk edit diri sendiri.');
        }

        return view('employee.edit', compact('user'));
    }

    // Mengupdate data karyawan
    public function update(Request $request, User $user)
    {
        // Pastikan tidak update diri sendiri dari sini
        if ($user->id === auth()->id()) {
            abort(403, 'Gunakan menu profil untuk update diri sendiri.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username,' . $user->id],
            'pin' => ['nullable', 'string', 'min:4', 'max:6', 'confirmed'],
            'role' => ['required', 'string', 'in:admin,employee,staff gudang'],
        ]);

        $before = $user->toArray();

        $data = [
            'name' => $request->name,
            'username' => $request->username,
            'role' => $request->role,
        ];

        // Update PIN jika diisi
        if ($request->filled('pin')) {
            $data['pin'] = Hash::make($request->pin);
        }

        $user->update($data);

        $action = ($before['role'] !== $request->role) ? 'user role changed' : 'user info edited';

        $beforeLog = $before;
        $beforeLog['pin'] = '******';
        
        $afterLog = $user->fresh()->toArray();
        $afterLog['pin'] = '******';

        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'details' => [
                'before' => $beforeLog,
                'after' => $afterLog
            ]
        ]);

        return redirect()->route('employee.index')
            ->with('success', 'Data karyawan berhasil diperbarui.');
    }

    // Menghapus karyawan
    public function destroy(User $user)
    {
        // Pastikan tidak hapus diri sendiri
        if ($user->id === auth()->id()) {
            abort(403, 'Tidak dapat menghapus akun sendiri.');
        }

        $before = $user->toArray();
        $user->delete();

        $logBefore = $before;
        $logBefore['pin'] = '******';

        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'user deleted',
            'details' => $logBefore
        ]);

        return redirect()->route('employee.index')
            ->with('success', 'Karyawan berhasil dihapus.');
    }
}