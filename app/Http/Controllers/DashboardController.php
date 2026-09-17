<?php

namespace App\Http\Controllers;

use App\Models\MonitoringLocation;
use App\Models\WaterMonitoring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Rentang tanggal yang tersedia sebagai preset cepat.
     *
     * @var array<string, string>
     */
    protected const DATE_RANGE_PRESETS = [
        'hari_ini' => 'Hari Ini',
        '7hari' => '7 Hari Terakhir',
        'bulan_ini' => 'Bulan Ini',
    ];

    /**
     * Tampilkan dashboard monitoring.
     *
     * Admin melihat seluruh data, petugas hanya melihat data miliknya.
     * Statistik (kartu & chart) mengikuti rentang tanggal yang dipilih,
     * sedangkan status terakhir per lokasi dan riwayat terbaru tetap
     * menampilkan data terbaru.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $today = now()->toDateString();

        [$dari, $sampai] = $this->resolveDateRange($request, $today);

        $base = WaterMonitoring::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('user_id', $user->id));

        $monitorings = (clone $base)->latest('tanggal')->latest('waktu');

        $rangeBase = (clone $base)->whereBetween('tanggal', [$dari, $sampai]);

        $total = (clone $base)->count();
        $rangeCount = (clone $rangeBase)->count();

        $rangeCounts = (clone $rangeBase)
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

        $warningLocations = (clone $base)
            ->where('kondisi', '!=', 'normal')
            ->with(['user', 'location'])
            ->orderBy('tanggal')
            ->orderBy('waktu')
            ->orderBy('id')
            ->get();

        $activLocationsCount = MonitoringLocation::where('status', 'aktif')->count();
        $locations = MonitoringLocation::where('status', 'aktif')
            ->orderBy('nama_lokasi')
            ->get();

        $chartLabels = array_values(WaterMonitoring::KONDISI);
        $chartData = [];
        foreach (WaterMonitoring::KONDISI as $key => $label) {
            $chartData[] = $rangeCounts[$key] ?? 0;
        }

        return view('dashboard', [
            'isAdmin' => $user->isAdmin(),
            'total' => $total,
            'rangeCount' => $rangeCount,
            'dateDari' => $dari,
            'dateSampai' => $sampai,
            'rangeLabel' => $this->rangeLabel($request, $dari, $sampai),
            'activeLocationsCount' => $activLocationsCount,
            'warningLocations' => $warningLocations,
            'latestByLocation' => $latestByLocation,
            'locations' => $locations,
            'rangeCounts' => $rangeCounts,
            'chartLabels' => $chartLabels,
            'chartData' => $chartData,
            'recent' => (clone $monitorings)->limit(10)->get(),
        ]);
    }

    /**
     * Tentukan rentang [tanggal awal, tanggal akhir] berdasarkan request.
     *
     * Prioritas: preset -> input dari/sampai manual -> hari ini.
     * Tanggal akhir dibatasi tidak melebihi hari ini.
     *
     * @return array{0: string, 1: string}
     */
    protected function resolveDateRange(Request $request, string $today): array
    {
        $preset = $request->string('preset')->toString();

        if (array_key_exists($preset, self::DATE_RANGE_PRESETS)) {
            return match ($preset) {
                '7hari' => [now()->subDays(6)->toDateString(), $today],
                'bulan_ini' => [now()->startOfMonth()->toDateString(), $today],
                default => [$today, $today],
            };
        }

        $dari = $this->parseDate($request->string('dari')->toString());
        $sampai = $this->parseDate($request->string('sampai')->toString());

        if ($dari && $sampai && $dari <= $sampai) {
            $sampai = min($sampai, $today);
            $dari = min($dari, $sampai);

            if ($dari <= $sampai) {
                return [$dari, $sampai];
            }
        }

        return [$today, $today];
    }

    /**
     * Label tampilan untuk rentang tanggal yang sedang aktif.
     */
    protected function rangeLabel(Request $request, string $dari, string $sampai): string
    {
        $preset = $request->string('preset')->toString();

        if (array_key_exists($preset, self::DATE_RANGE_PRESETS)) {
            return self::DATE_RANGE_PRESETS[$preset];
        }

        $format = fn (string $date) => \Carbon\Carbon::parse($date)->format('d/m/Y');

        return $dari === $sampai ? $format($dari) : $format($dari).' – '.$format($sampai);
    }

    /**
     * Validasi tanggal format Y-m-d dan kembalikan string normal, atau null bila tidak valid.
     */
    protected function parseDate(string $value): ?string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', [$matches[1], $matches[2], $matches[3]]);

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
