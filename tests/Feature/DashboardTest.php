<?php

namespace Tests\Feature;

use App\Models\FinanceLocation;
use App\Models\FinanceReport;
use App\Models\MonitoringLocation;
use App\Models\User;
use App\Models\WaterMonitoring;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_loads_for_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Dashboard Monitoring');
    }

    public function test_dashboard_loads_for_petugas_without_data(): void
    {
        $petugas = User::factory()->petugas()->create();

        $response = $this->actingAs($petugas)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Dashboard Monitoring');
    }

    public function test_dashboard_finance_section_count_current_month(): void
    {
        $admin = User::factory()->admin()->create();
        $petugas = User::factory()->petugas()->create();
        $location = FinanceLocation::factory()->create();
        $bulan = now()->format('Y-m');

        FinanceReport::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->format('Y-m-d'),
            'kondisi' => 'normal',
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('financeTotal'));
        $this->assertSame(1, $response->viewData('financeCounts')['normal']);
    }

    public function test_finance_section_endpoint_returns_partial_html(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(
            route('dashboard.finance-section', ['bulan' => now()->format('Y-m')])
        );

        $response->assertOk();
        $response->assertSee('Laporan Bulanan');
    }

    public function test_dashboard_renders_warning_for_non_normal_condition(): void
    {
        $admin = User::factory()->admin()->create();
        $petugas = User::factory()->petugas()->create();
        $location = MonitoringLocation::factory()->create();

        \App\Models\WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'tanggal' => now()->format('Y-m-d'),
            'sesi' => 'pagi',
            'waktu' => '08:00',
            'kondisi' => 'debit_turun',
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('butuh perhatian');
        $response->assertSee('Debit Turun');
    }

    /**
     * Regression test untuk N+1 di dashboard.
     *
     * preventLazyLoading() membuat Eloquent melempar exception begitu
     * relasi diakses tanpa eager loading. Karena view dashboard memakai
     * $recent->user->name dan $recent->location->nama_lokasi, test ini
     * akan gagal bila query 'recent' kembali tanpa with(['user','location']).
     */
    public function test_dashboard_does_not_lazy_load_relations(): void
    {
        Model::preventLazyLoading(true);

        try {
            $admin = User::factory()->admin()->create();
            $petugas = User::factory()->petugas()->create();
            $lokasi = MonitoringLocation::factory()->count(4)->create();
            $financeLocation = FinanceLocation::factory()->create();

            $sesiList = ['pagi', 'siang', 'sore'];

            foreach ($lokasi as $i => $loc) {
                foreach ($sesiList as $sesi) {
                    WaterMonitoring::factory()->create([
                        'user_id' => $petugas->id,
                        'location_id' => $loc->id,
                        'tanggal' => now()->subDays($i)->toDateString(),
                        'sesi' => $sesi,
                        'waktu' => WaterMonitoring::SESI_WAKTU[$sesi],
                        'kondisi' => 'debit_turun',
                    ]);
                }
            }

            foreach ($sesiList as $index => $unused) {
                FinanceReport::factory()->create([
                    'user_id' => $petugas->id,
                    'location_id' => $financeLocation->id,
                    'tanggal' => now()->subDays($index)->toDateString(),
                ]);
            }

            $response = $this->actingAs($admin)->get(route('dashboard'));

            $response->assertOk();
            $this->assertCount(10, $response->viewData('recent'));
        } finally {
            Model::preventLazyLoading(false);
        }
    }
}