<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title')</title>
    <!-- Font Awesome Local -->
    <link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
    <!-- Main CSS -->
    <link rel="stylesheet" href="{{ asset('css/app-styles.css') }}">
    @stack('styles')
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h1>DTHREE STUDIO</h1>
            <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        </div>
        
        <nav class="sidebar-nav">
            @if(Auth::check())
                @if(Auth::user()->role === 'admin')
                    <a href="{{ route('dashboard.admin') }}" class="nav-item {{ request()->is('dashboard/admin') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fas fa-chart-line"></i></span>
                        <span class="nav-text">Dashboard Admin</span>
                    </a>
                @else
                    <a href="{{ route('dashboard.employee') }}" class="nav-item {{ request()->is('dashboard/employee') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fas fa-chart-line"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                @endif
                
                <a href="{{ route('transaction.index') }}" class="nav-item {{ request()->is('kasir') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fas fa-cash-register"></i></span>
                    <span class="nav-text">Kasir</span>
                </a>
                
                <a href="{{ route('products.index') }}" class="nav-item {{ request()->is('products*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fas fa-boxes"></i></span>
                    <span class="nav-text">Manajemen Produk</span>
                </a>
                
                <a href="{{ route('booking.index') }}" class="nav-item {{ request()->is('booking*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fas fa-calendar-alt"></i></span>
                    <span class="nav-text">Booking Studio</span>
                </a>
                
                <a href="{{ route('transaction.history') }}" class="nav-item {{ request()->is('transaksi/riwayat') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fas fa-history"></i></span>
                    <span class="nav-text">Riwayat Transaksi</span>
                </a>
                
                @if(Auth::user()->role === 'admin')
                    <a href="{{ route('employee.index') }}" class="nav-item {{ request()->is('karyawan*') ? 'active' : '' }}">
                        <span class="nav-icon"><i class="fas fa-users"></i></span>
                        <span class="nav-text">Manajemen Karyawan</span>
                    </a>
                @endif
                
                <a href="{{ route('settings.index') }}" class="nav-item {{ request()->is('pengaturan') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fas fa-cog"></i></span>
                    <span class="nav-text">Pengaturan</span>
                </a>
            @endif
        </nav>
        
        @if(Auth::check())
        <div class="user-info flex items-center">
            <div class="user-avatar">
                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
            </div>
            <div class="user-details">
                <div class="user-name">{{ Auth::user()->name }}</div>
                <div class="user-role">{{ ucfirst(Auth::user()->role) }}</div>
            </div>
            <form action="{{ route('logout') }}" method="POST" style="margin-left: auto;">
                @csrf
                <button type="submit" class="btn btn-danger text-sm" style="padding: 4px 8px;">Logout</button>
            </form>
        </div>
        @endif
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="top-bar">
            <div class="flex items-center gap-4">
                <button class="sidebar-toggle mobile" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                <h2 class="page-title">@yield('page-title', 'Dashboard')</h2>
            </div>
            <div class="flex items-center gap-2">
                <button id="themeToggle" class="btn btn-secondary" style="background: var(--secondary); color: white; padding: 6px 12px;">
                    <i class="fas fa-moon"></i> <span class="theme-text">Dark Mode</span>
                </button>
                @yield('header-actions')
            </div>
        </div>
        
        <div class="content-wrapper">
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            
            @if(session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif
            
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul style="margin: 0; padding-left: 20px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            
            @yield('content')
        </div>
    </div>
    
    <!-- Main JavaScript -->
    <script src="{{ asset('js/app-scripts.js') }}"></script>
    @stack('scripts')
</body>
</html>
