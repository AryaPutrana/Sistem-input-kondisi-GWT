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

        $base = WaterMonitoring::query()
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

        $latestSub = (clone $base)
            ->select('water_monitorings.*')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY location_id ORDER BY tanggal DESC, waktu DESC) AS rn');

        $latestByLocation = WaterMonitoring::query()
            ->fromSub($latestSub->toBase(), 'latest')
            ->where('rn', 1)
            ->with(['user', 'location'])
            ->get()
            ->keyBy('location_id');

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
