@extends('layouts.app')

@section('title', 'Manajemen Karyawan')
@section('page-title', 'Manajemen Karyawan')

@section('header-actions')
    <a href="{{ route('employee.create') }}" class="btn btn-success">
        👥 Tambah Karyawan
    </a>
@endsection

@section('content')
<style>
    .bg-brown-100 {
        background-color: #f5e6d3;
    }
    
    .text-brown-800 {
        color: #8b4513;
    }
    
    .mx-2 {
        margin-right: 0.5rem;
    }

    .bg-purple-100 {
        background-color: #e9d5ff;
    }
    
    .text-purple-800 {
        color: #5b21b6;
    }
    
    .bg-blue-100 {
        background-color: #dbeafe;
    }
    
    .text-blue-800 {
        color: #1e40af;
    }
    
    .font-mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }
</style>
<div class="card">
    <div class="card-header flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900">Daftar Karyawan</h3>
        <div class="text-sm text-gray-600">
            Total: {{ $employees->count() }} karyawan
        </div>
    </div>
    <div class="card-body">
        @if(session('success'))
        <div class="alert alert-success mb-4">
            {{ session('success') }}
        </div>
        @endif
        
        @if($employees->count() > 0)
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Bergabung</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($employees as $employee)
                    <tr>
                        <td>
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8 flex items-center justify-center rounded-full mx-2 bg-brown-100 text-brown-800 font-bold text-sm">
                                    {{ strtoupper(substr($employee->name, 0, 1)) }}
                                </div>
                                <div class="ml-2">
                                    <div class="font-medium">{{ $employee->name }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="font-mono text-sm">{{ $employee->username }}</td>
                        <td>
                            @if($employee->role === 'admin')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-purple-100 text-purple-800">
                                Admin
                            </span>
                            @elseif($employee->role === 'staff gudang')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-green-100 text-green-800">
                                Staff Gudang
                            </span>
                            @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-badge text-xs font-medium bg-blue-100 text-blue-800">
                                Kasir
                            </span>
                            @endif
                        </td>
                        <td class="text-sm text-gray-600">{{ $employee->created_at->format('d/m/Y') }}</td>
                        <td>
                            <div class="flex gap-2">
                                <a href="{{ route('employee.edit', $employee) }}" class="btn btn-warning text-sm">
                                    ✏️ Edit
                                </a>
                                <form action="{{ route('employee.destroy', $employee) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus karyawan ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger text-sm">
                                        🗑️ Hapus
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
        <div class="text-center py-8 text-gray-600">
            <div class="text-4xl mb-2">👥</div>
            <p class="text-lg font-medium mb-2">Belum ada karyawan</p>
            <p class="text-sm mb-4">Tambahkan karyawan pertama Anda untuk mulai menggunakan sistem.</p>
            <a href="{{ route('employee.create') }}" class="btn btn-success">
                👥 Tambah Karyawan Pertama
            </a>
        </div>
        @endif
    </div>
</div>

<!-- Add Employee Modal -->
<div class="modal" id="addEmployeeModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-gray-900">Tambah Karyawan Baru</h3>
            <button type="button" onclick="closeModal('addEmployeeModal')" class="btn btn-danger">✕</button>
        </div>
        <div class="modal-body">
            <form id="addEmployeeForm" method="POST" action="{{ route('employee.store') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">PIN (4-6 digit)</label>
                    <input type="password" name="pin" class="form-control" required minlength="4" maxlength="6">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control" required>
                        <option value="employee">Kasir</option>
                        <option value="staff gudang">Staff Gudang</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('addEmployeeModal')" class="btn btn-secondary">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openAddEmployeeModal() {
        openModal('addEmployeeModal');
    }
    
    // Handle form submission
    document.getElementById('addEmployeeForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const pin = formData.get('pin');
        
        // Validate PIN
        if (pin.length < 4 || pin.length > 6) {
            alert('PIN harus 4-6 digit');
            return;
        }
        
        // Submit form
        this.submit();
    });
</script>
@endsection