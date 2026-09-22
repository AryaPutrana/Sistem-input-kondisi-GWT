<?php

namespace Tests\Feature;

use App\Models\MonitoringLocation;
use App\Models\WaterMonitoring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/monitoring')->assertRedirect(route('login'));
        $this->get('/pengguna')->assertRedirect(route('login'));
    }

    public function test_petugas_cannot_access_admin_pages(): void
    {
        $petugas = User::factory()->petugas()->create();

        $this->actingAs($petugas)
            ->get(route('pengguna.index'))->assertForbidden();
        $this->actingAs($petugas)
            ->get(route('lokasi.index'))->assertForbidden();
        $this->actingAs($petugas)
            ->get(route('lokasiBulanan.index'))->assertForbidden();
        $this->actingAs($petugas)
            ->get(route('keuangan.index'))->assertForbidden();
        $this->actingAs($petugas)
            ->get(route('keuangan.pdf', ['bulan' => now()->format('Y-m')]))->assertForbidden();
    }

    public function test_admin_cannot_access_petugas_input_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('monitoring.create'))->assertForbidden();
        $this->actingAs($admin)
            ->get(route('keuangan.create'))->assertForbidden();
        $this->actingAs($admin)
            ->get(route('keuangan.riwayat'))->assertForbidden();
    }

    public function test_petugas_cannot_modify_other_petugas_monitoring(): void
    {
        $owner = User::factory()->petugas()->create();
        $other = User::factory()->petugas()->create();
        $location = MonitoringLocation::factory()->create();
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $owner->id,
            'location_id' => $location->id,
        ]);

        $this->actingAs($other)->get(route('monitoring.show', $monitoring))->assertForbidden();
        $this->actingAs($other)->get(route('monitoring.edit', $monitoring))->assertForbidden();
        $this->actingAs($other)->delete(route('monitoring.destroy', $monitoring))->assertForbidden();

        $response = $this->actingAs($other)->put(route('monitoring.update', $monitoring), [
            'location_id' => $location->id,
            'tanggal' => $monitoring->tanggal->format('Y-m-d'),
            'sesi' => $monitoring->sesi,
            'kondisi' => 'normal',
            'keterangan' => 'ubah oleh orang lain',
        ]);
        $response->assertForbidden();

        $this->assertDatabaseHas('water_monitorings', [
            'id' => $monitoring->id,
            'user_id' => $owner->id,
            'keterangan' => $monitoring->keterangan,
        ]);
    }

    public function test_petugas_can_access_own_monitoring(): void
    {
        $petugas = User::factory()->petugas()->create();
        $location = MonitoringLocation::factory()->create();
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
        ]);

        $this->actingAs($petugas)->get(route('monitoring.show', $monitoring))->assertOk();
        $this->actingAs($petugas)->get(route('monitoring.edit', $monitoring))->assertOk();
        $this->actingAs($petugas)
            ->delete(route('monitoring.destroy', $monitoring))
            ->assertRedirect();
        $this->assertDatabaseMissing('water_monitorings', ['id' => $monitoring->id]);
    }

    public function test_admin_can_modify_any_monitoring(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->petugas()->create();
        $location = MonitoringLocation::factory()->create();
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $owner->id,
            'location_id' => $location->id,
        ]);

        $this->actingAs($admin)->get(route('monitoring.show', $monitoring))->assertOk();
        $this->actingAs($admin)->get(route('monitoring.edit', $monitoring))->assertOk();

        $response = $this->actingAs($admin)->put(route('monitoring.update', $monitoring), [
            'location_id' => $location->id,
            'tanggal' => $monitoring->tanggal->format('Y-m-d'),
            'sesi' => $monitoring->sesi,
            'kondisi' => 'debit_turun',
            'keterangan' => 'diperbarui oleh admin',
        ]);
        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('water_monitorings', [
            'id' => $monitoring->id,
            'kondisi' => 'debit_turun',
            'keterangan' => 'diperbarui oleh admin',
        ]);
    }
}