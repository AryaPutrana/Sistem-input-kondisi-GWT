<?php

namespace App\Http\Controllers;

use App\Models\MonitoringLocation;
use App\Models\WaterMonitoring;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Tampilkan dashboard monitoring.
     *
     * Admin melihat seluruh data, petugas hanya melihat data miliknya.
     */
    public function index(): View
    {
        $user = Auth::user();
        $today = now()->toDateString();

        $base = WaterMonitoring::with(['user', 'location'])
            ->when(! $user->isAdmin(), fn ($query) => $query->where('user_id', $user->id));

        $monitorings = (clone $base)->latest('tanggal')->latest('waktu');

        $total = (clone $base)->count();
        $todayCount = (clone $base)->whereDate('tanggal', $today)->count();

        $todayCounts = (clone $base)
            ->whereDate('tanggal', $today)
            ->selectRaw('kondisi, count(*) as jumlah')
            ->groupBy('kondisi')
            ->pluck('jumlah', 'kondisi')
            ->toArray();

        $all = (clone $monitorings)->get();

        $latestByLocation = collect();
        foreach ($all as $wm) {
            $latestByLocation->put($wm->location_id, $wm);
        }

        $warningLocations = $latestByLocation
            ->filter(fn ($wm) => $wm->kondisi !== 'normal')
            ->values();

        $activLocationsCount = MonitoringLocation::where('status', 'aktif')->count();
        $locations = MonitoringLocation::where('status', 'aktif')
            ->orderBy('nama_lokasi')
            ->get();

        $chartLabels = array_values(WaterMonitoring::KONDISI);
        $chartData = [];
        foreach (WaterMonitoring::KONDISI as $key => $label) {
            $chartData[] = $todayCounts[$key] ?? 0;
        }

        return view('dashboard', [
            'isAdmin' => $user->isAdmin(),
            'total' => $total,
            'todayCount' => $todayCount,
            'activeLocationsCount' => $activLocationsCount,
            'warningLocations' => $warningLocations,
            'latestByLocation' => $latestByLocation,
            'locations' => $locations,
            'todayCounts' => $todayCounts,
            'chartLabels' => $chartLabels,
            'chartData' => $chartData,
            'recent' => (clone $monitorings)->limit(10)->get(),
        ]);
    }
}
