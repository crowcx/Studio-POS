@extends('layouts.app')

@section('title', 'Tambah Karyawan Baru')
@section('page-title', 'Tambah Karyawan Baru')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="text-lg font-semibold text-gray-900">Form Tambah Karyawan</h3>
    </div>
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger mb-6">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        
        <form method="POST" action="{{ route('employee.store') }}">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="form-group">
                    <label class="form-label" for="name">Nama Lengkap</label>
                    <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" required>
                    @error('name')
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" value="{{ old('username') }}" required>
                    <div class="text-gray-500 text-sm mt-1">Username digunakan untuk login ke sistem</div>
                    @error('username')
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="pin">PIN (4-6 digit)</label>
                    <input type="password" id="pin" name="pin" class="form-control" required minlength="4" maxlength="6">
                    <div class="text-gray-500 text-sm mt-1">PIN harus terdiri dari 4-6 digit angka</div>
                    @error('pin')
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="pin_confirmation">Konfirmasi PIN</label>
                    <input type="password" id="pin_confirmation" name="pin_confirmation" class="form-control" required minlength="4" maxlength="6">
                </div>
                
                <div class="form-group md:col-span-2">
                    <label class="form-label" for="role">Role Karyawan</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="employee" {{ old('role') == 'employee' ? 'selected' : '' }}>Kasir</option>
                        <option value="staff gudang" {{ old('role') == 'staff gudang' ? 'selected' : '' }}>Staff Gudang (Manager Produk)</option>
                        <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                    </select>
                    @error('role')
                        <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="flex gap-2 mt-8">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-1"></i> Simpan Karyawan
                </button>
                <a href="{{ route('employee.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times mr-1"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>
@endsection