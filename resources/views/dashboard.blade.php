@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold">Dashboard Monitoring</h4>
    @if (! $isAdmin)
        <a href="{{ route('monitoring.create') }}" class="btn btn-primary">+ Input Monitoring</a>
    @else
        <a href="{{ route('monitoring.index') }}" class="btn btn-outline-primary">Lihat Histori</a>
    @endif
</div>

@if ($total === 0)
    <div class="alert alert-info" role="alert">
        Belum ada data pemeriksaan. Mulai dari input pemeriksaan pertama untuk melihat status monitoring di dashboard ini.
    </div>
@endif

@if ($warningLocations->isNotEmpty())
    <div class="alert alert-danger d-flex align-items-start" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-exclamation-triangle-fill me-2 flex-shrink-0 mt-1" viewBox="0 0 16 16">
            <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/>
        </svg>
        <div>
            <strong>{{ $warningLocations->count() }} catatan pemeriksaan butuh perhatian:</strong>
            <ul class="mb-0">
                @foreach ($warningLocations as $wm)
                    <li>
                        <strong>{{ $wm->location->nama_lokasi }}</strong>
                        — {{ \App\Models\WaterMonitoring::KONDISI[$wm->kondisi] ?? ucfirst($wm->kondisi) }}
                        ({{ $wm->tanggal->format('d/m/Y') }} {{ $wm->waktu->format('H:i') }})
                        <a href="{{ route('monitoring.show', $wm) }}" class="text-decoration-none">Lihat Detail</a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

@php
    $kondisiBadge = fn ($kondisi) => $kondisi === 'normal'
        ? 'bg-success'
        : 'bg-warning text-dark';
    $kondisiLabel = fn ($kondisi) => \App\Models\WaterMonitoring::KONDISI[$kondisi] ?? ucfirst($kondisi);
@endphp

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Total Pemeriksaan</div>
                <div class="fs-3 fw-bold">{{ $total }}</div>
                <span class="badge bg-primary">{{ $isAdmin ? 'Semua lokasi' : 'Milik Anda' }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Pemeriksaan Hari Ini</div>
                <div class="fs-3 fw-bold">{{ $todayCount }}</div>
                <span class="badge bg-info text-dark">{{ now()->format('d/m/Y') }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Lokasi Aktif</div>
                <div class="fs-3 fw-bold">{{ $activeLocationsCount }}</div>
                <span class="badge bg-secondary">Terdaftar</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm h-100 border-danger">
            <div class="card-body">
                <div class="text-muted small">Catatan Perlu Perhatian</div>
                <div class="fs-3 fw-bold text-danger">{{ $warningLocations->count() }}</div>
                <span class="badge bg-danger">Cek segera</span>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold">Status Terakhir per Lokasi</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Lokasi</th>
                            <th scope="col">Status</th>
                            <th scope="col">Terakhir</th>
                            @if ($isAdmin)
                                <th scope="col">Petugas</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($locations as $location)
                            @php $wm = $latestByLocation->get($location->id); @endphp
                            <tr>
                                <td>{{ $location->nama_lokasi }}</td>
                                @if ($wm)
                                    <td><span class="badge {{ $kondisiBadge($wm->kondisi) }}">{{ $kondisiLabel($wm->kondisi) }}</span></td>
                                    <td>{{ $wm->tanggal->format('d/m/Y') }} {{ $wm->waktu->format('H:i') }}</td>
                                    @if ($isAdmin)
                                        <td>{{ $wm->user->name }}</td>
                                    @endif
                                @else
                                    <td><span class="badge bg-light text-secondary">Belum ada</span></td>
                                    <td>—</td>
                                    @if ($isAdmin)
                                        <td>—</td>
                                    @endif
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdmin ? 4 : 3 }}" class="text-center text-muted py-4">
                                    Belum ada lokasi aktif.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold">Rekap Kondisi Hari Ini</div>
            <div class="card-body">
                <div style="height: 260px">
                    <canvas id="kondisiChart"></canvas>
                </div>
                <div class="row text-center mt-3 g-2">
                    @foreach ($chartLabels as $i => $label)
                        <div class="col-4">
                            <span class="badge {{ $kondisiBadge(array_keys(\App\Models\WaterMonitoring::KONDISI)[$i]) }}">{{ $label }}</span>
                            <div class="fs-5 fw-bold mt-1">{{ $chartData[$i] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
        <span>Riwayat Pemeriksaan Terbaru</span>
        <a href="{{ route('monitoring.index') }}" class="small text-decoration-none">Lihat semua</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Waktu</th>
                    <th scope="col">Lokasi</th>
                    <th scope="col">Sesi</th>
                    <th scope="col">Kondisi</th>
                    <th scope="col">Keterangan</th>
                    @if ($isAdmin)
                        <th scope="col">Petugas</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($recent as $i => $wm)
                    <tr>
                        <td>{{ $wm->tanggal->format('d/m/Y') }}</td>
                        <td>{{ $wm->waktu->format('H:i') }}</td>
                        <td>{{ $wm->location->nama_lokasi }}</td>
                        <td>{{ \App\Models\WaterMonitoring::SESI[$wm->sesi] ?? ucfirst($wm->sesi) }}</td>
                        <td><span class="badge {{ $kondisiBadge($wm->kondisi) }}">{{ $kondisiLabel($wm->kondisi) }}</span></td>
                        <td>{{ \Illuminate\Support\Str::limit($wm->keterangan, 40) }}</td>
                        @if ($isAdmin)
                            <td>{{ $wm->user->name }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isAdmin ? 7 : 6 }}" class="text-center text-muted py-4">
                            Belum ada data pemeriksaan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const ctx = document.getElementById('kondisiChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: @json($chartLabels),
                datasets: [{
                    data: @json($chartData),
                    backgroundColor: ['#198754', '#ffc107', '#dc3545'],
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                },
            },
        });
    }
</script>
@endpush