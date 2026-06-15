@extends('layouts.app')

@section('title', 'Edit Data Karyawan')
@section('page-title', 'Edit Data Karyawan')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="text-lg font-semibold text-gray-900">Form Edit Karyawan</h3>
    </div>
    <div class="card-body">
        <div class="bg-gray-50 p-4 rounded-lg mb-6">
            <h4 class="font-medium text-gray-700 mb-2">Informasi Saat Ini</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Nama</p>
                    <p class="font-medium">{{ $user->name }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Username</p>
                    <p class="font-medium">{{ $user->username }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Bergabung</p>
                    <p class="font-medium">{{ $user->created_at->format('d F Y') }}</p>
                </div>
            </div>
        </div>
        
        @if(session('success'))
            <div class="alert alert-success mb-6">
                {{ session('success') }}
            </div>
        @endif
        
        @if($errors->any())
            <div class="alert alert-danger mb-6">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        
        <form method="POST" action="{{ route('employee.update', $user) }}">
            @csrf
            @method('PUT')
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="form-group">
                    <label class="form-label" for="name">Nama Lengkap</label>
                    <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                    @error('name')
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" value="{{ old('username', $user->username) }}" required>
                    <div class="text-gray-500 text-sm mt-1">Username digunakan untuk login ke sistem</div>
                    @error('username')
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="pin">PIN Baru (Opsional)</label>
                    <input type="password" id="pin" name="pin" class="form-control" minlength="4" maxlength="6">
                    <div class="text-gray-500 text-sm mt-1">Kosongkan jika tidak ingin mengubah PIN. PIN harus 4-6 digit angka.</div>
                    @error('pin')
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="pin_confirmation">Konfirmasi PIN Baru</label>
                    <input type="password" id="pin_confirmation" name="pin_confirmation" class="form-control" minlength="4" maxlength="6">
                </div>
                
                <div class="form-group md:col-span-2">
                    <label class="form-label" for="role">Role Karyawan</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="employee" {{ old('role', $user->role) == 'employee' ? 'selected' : '' }}>Kasir</option>
                        <option value="staff gudang" {{ old('role', $user->role) == 'staff gudang' ? 'selected' : '' }}>Staff Gudang (Manager Produk)</option>
                        <option value="admin" {{ old('role', $user->role) == 'admin' ? 'selected' : '' }}>Admin</option>
                    </select>
                    @error('role')
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="flex gap-2 mt-8">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-1"></i> Simpan Perubahan
                </button>
                <a href="{{ route('employee.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times mr-1"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>
@endsection