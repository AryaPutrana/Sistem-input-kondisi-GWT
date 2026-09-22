<?php

namespace Tests\Feature;

use App\Models\MonitoringLocation;
use App\Models\WaterMonitoring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MonitoringCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_petugas_can_store_monitoring(): void
    {
        Storage::fake('public');

        $petugas = User::factory()->petugas()->create();
        $location = MonitoringLocation::factory()->create();

        $response = $this->actingAs($petugas)->post(route('monitoring.store'), [
            'location_id' => $location->id,
            'tanggal' => now()->format('Y-m-d'),
            'sesi' => 'pagi',
            'kondisi' => 'normal',
            'keterangan' => 'Kondisi air normal.',
            'foto' => UploadedFile::fake()->image('foto.jpg'),
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $record = WaterMonitoring::first();
        $this->assertNotNull($record);
        $this->assertDatabaseHas('water_monitorings', [
            'id' => $record->id,
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'sesi' => 'pagi',
            'waktu' => '08:00:00',
            'kondisi' => 'normal',
        ]);
        Storage::disk('public')->assertExists($record->foto);
    }

    public function test_foto_is_required(): void
    {
        $petugas = User::factory()->petugas()->create();
        $location = MonitoringLocation::factory()->create();

        $response = $this->actingAs($petugas)->post(route('monitoring.store'), [
            'location_id' => $location->id,
            'tanggal' => now()->format('Y-m-d'),
            'sesi' => 'pagi',
            'kondisi' => 'normal',
            'keterangan' => 'Tanpa foto.',
        ]);

        $response->assertSessionHasErrors('foto');
        $this->assertDatabaseCount('water_monitorings', 0);
    }

    public function test_duplicate_location_date_session_is_rejected(): void
    {
        $petugas = User::factory()->petugas()->create();
        $location = MonitoringLocation::factory()->create();

        WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'tanggal' => '2026-09-01',
            'sesi' => 'pagi',
            'waktu' => '08:00',
        ]);

        $response = $this->actingAs($petugas)->post(route('monitoring.store'), [
            'location_id' => $location->id,
            'tanggal' => '2026-09-01',
            'sesi' => 'pagi',
            'kondisi' => 'normal',
            'keterangan' => 'Duplikat.',
            'foto' => UploadedFile::fake()->image('foto.jpg'),
        ]);

        $response->assertSessionHasErrors('location_id');
        $this->assertDatabaseCount('water_monitorings', 1);
    }

    public function test_non_normal_condition_shows_warning_flash(): void
    {
        $petugas = User::factory()->petugas()->create();
        $location = MonitoringLocation::factory()->create();

        $response = $this->actingAs($petugas)->post(route('monitoring.store'), [
            'location_id' => $location->id,
            'tanggal' => now()->format('Y-m-d'),
            'sesi' => 'siang',
            'kondisi' => 'debit_turun',
            'keterangan' => 'Debit air menurun.',
            'foto' => UploadedFile::fake()->image('foto.jpg'),
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('water_monitorings', [
            'location_id' => $location->id,
            'kondisi' => 'debit_turun',
        ]);
    }

    public function test_index_shows_only_own_data_for_petugas(): void
    {
        $petugas = User::factory()->petugas()->create();
        $other = User::factory()->petugas()->create();
        $location = MonitoringLocation::factory()->create();

        WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'tanggal' => '2026-09-01',
            'sesi' => 'pagi',
            'waktu' => '08:00',
        ]);
        WaterMonitoring::factory()->create([
            'user_id' => $other->id,
            'location_id' => $location->id,
            'tanggal' => '2026-09-01',
            'sesi' => 'siang',
            'waktu' => '12:00',
        ]);

        $response = $this->actingAs($petugas)->get(route('monitoring.index'));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('monitorings')->total());
    }

    public function test_index_shows_all_data_for_admin(): void
    {
        $petugas = User::factory()->petugas()->create();
        $other = User::factory()->petugas()->create();
        $location = MonitoringLocation::factory()->create();

        WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'tanggal' => '2026-09-01',
            'sesi' => 'pagi',
            'waktu' => '08:00',
        ]);
        WaterMonitoring::factory()->create([
            'user_id' => $other->id,
            'location_id' => $location->id,
            'tanggal' => '2026-09-01',
            'sesi' => 'siang',
            'waktu' => '12:00',
        ]);

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('monitoring.index'));

        $response->assertOk();
        $this->assertSame(2, $response->viewData('monitorings')->total());
    }

    public function test_index_filters_by_location_and_date(): void
    {
        $petugas = User::factory()->petugas()->create();
        $locA = MonitoringLocation::factory()->create();
        $locB = MonitoringLocation::factory()->create();

        WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $locA->id,
            'tanggal' => '2026-09-01',
            'sesi' => 'pagi',
            'waktu' => '08:00',
        ]);
        WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $locB->id,
            'tanggal' => '2026-09-02',
            'sesi' => 'pagi',
            'waktu' => '08:00',
        ]);
        WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $locB->id,
            'tanggal' => '2026-09-01',
            'sesi' => 'sore',
            'waktu' => '16:00',
        ]);

        $response = $this->actingAs($petugas)->get(route('monitoring.index', [
            'lokasi' => $locB->id,
            'tanggal' => '2026-09-01',
        ]));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('monitorings')->total());
    }

    public function test_update_keeps_old_photo_when_not_replaced(): void
    {
        $petugas = User::factory()->petugas()->create();
        $location = MonitoringLocation::factory()->create();
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'tanggal' => '2026-09-01',
            'sesi' => 'pagi',
            'waktu' => '08:00',
            'foto' => 'monitoring/lama.jpg',
        ]);

        $response = $this->actingAs($petugas)->put(route('monitoring.update', $monitoring), [
            'location_id' => $location->id,
            'tanggal' => '2026-09-01',
            'sesi' => 'pagi',
            'kondisi' => 'normal',
            'keterangan' => 'Diperbarui tanpa ganti foto.',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('water_monitorings', [
            'id' => $monitoring->id,
            'foto' => 'monitoring/lama.jpg',
            'keterangan' => 'Diperbarui tanpa ganti foto.',
        ]);
    }

    public function test_destroy_removes_record_and_photo(): void
    {
        Storage::fake('public');

        $petugas = User::factory()->petugas()->create();
        $location = MonitoringLocation::factory()->create();
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'foto' => 'monitoring/hapus.jpg',
        ]);
        Storage::disk('public')->put($monitoring->foto, 'konten-foto');

        $response = $this->actingAs($petugas)->delete(route('monitoring.destroy', $monitoring));

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('water_monitorings', ['id' => $monitoring->id]);
        Storage::disk('public')->assertMissing($monitoring->foto);
    }
}