<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistem Monitoring GWT & Kolam Air Bersih')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body class="bg-light">

@auth
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a href="{{ route('dashboard') }}" class="navbar-brand">Monitoring Air Sarpras</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}" class="nav-link">Dashboard</a>
                </li>
                @if (Auth::user()->isAdmin())
                    <li class="nav-item">
                        <a href="{{ route('monitoring.index') }}" class="nav-link">Histori Monitoring</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('lokasi.index') }}" class="nav-link">Lokasi Monitoring</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('pengguna.index') }}" class="nav-link">Kelola Pengguna</a>
                    </li>
                @else
                    <li class="nav-item">
                        <a href="{{ route('monitoring.create') }}" class="nav-link">Input Monitoring</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('monitoring.index') }}" class="nav-link">Histori Saya</a>
                    </li>
                @endif
            </ul>
            <div class="d-flex align-items-center gap-2">
                <span class="text-white small">
                    {{ Auth::user()->name }} ({{ ucfirst(Auth::user()->role) }})
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">Keluar</button>
                </form>
            </div>
        </div>
    </div>
</nav>
@endauth

<main class="py-4">
    <div class="container">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @yield('content')
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>