<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistem Monitoring GWT & Kolam Air Bersih')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    @stack('styles')
</head>
<body class="@yield('bodyClass', 'bg-light')">

@auth
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a href="{{ route('dashboard') }}" class="navbar-brand">Sistem Sarana dan Prasarana</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}"
                       class="nav-link{{ request()->routeIs('dashboard') ? ' active' : '' }}">Dashboard</a>
                </li>
                @if (Auth::user()->isAdmin())
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" role="button" aria-expanded="false">Monitoring Harian</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('lokasi.index') }}">Lokasi Monitoring</a></li>
                            <li><a class="dropdown-item" href="{{ route('monitoring.index') }}">Histori Monitoring</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" role="button" aria-expanded="false">Laporan Bulanan</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('lokasiBulanan.index') }}">Lokasi Bulanan</a></li>
                            <li><a class="dropdown-item" href="{{ route('keuangan.index') }}">Laporan Bulanan</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('pengguna.index') }}" class="nav-link">Kelola Pengguna</a>
                    </li>
                @else
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" role="button" aria-expanded="false">Monitoring Harian</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('monitoring.create') }}">Input Monitoring</a></li>
                            <li><a class="dropdown-item" href="{{ route('monitoring.index') }}">Histori Monitoring</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" role="button" aria-expanded="false">Laporan Bulanan</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('keuangan.create') }}">Input Bulanan</a></li>
                            <li><a class="dropdown-item" href="{{ route('keuangan.riwayat') }}">Riwayat Bulanan</a></li>
                        </ul>
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

<!-- Modal global untuk melihat foto pemeriksaan (dipakai histori & detail) -->
<div class="modal fade" id="fotoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Foto Pemeriksaan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body text-center">
                <img id="fotoModalImg" src="" alt="Foto pemeriksaan" class="img-fluid rounded">
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var fotoModal = document.getElementById('fotoModal');
        var fotoImg = document.getElementById('fotoModalImg');
        if (! fotoModal || ! fotoImg) return;

        fotoModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            var src = btn && btn.getAttribute('data-foto-src');
            if (src) fotoImg.src = src;
        });

        fotoModal.addEventListener('hidden.bs.modal', function () {
            fotoImg.src = '';
        });
    });
</script>
@endpush

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>