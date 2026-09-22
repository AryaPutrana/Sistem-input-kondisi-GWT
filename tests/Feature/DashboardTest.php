<?php

namespace Tests\Feature;

use App\Models\FinanceLocation;
use App\Models\FinanceReport;
use App\Models\MonitoringLocation;
use App\Models\User;
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
}