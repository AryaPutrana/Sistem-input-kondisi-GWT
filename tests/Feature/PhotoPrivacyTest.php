<?php

namespace Tests\Feature;

use App\Models\FinanceLocation;
use App\Models\FinanceReport;
use App\Models\MonitoringLocation;
use App\Models\WaterMonitoring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class PhotoPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('evidence');
    }

    /*
     |--------------------------------------------------------------------------
     | 2.1 - foto tidak boleh bocor lewat /storage
     |--------------------------------------------------------------------------
     */

    public function test_evidence_disk_is_not_inside_the_public_storage_folder(): void
    {
        $publicRoot = realpath(config('filesystems.disks.public.root'));
        $evidenceRoot = realpath(config('filesystems.disks.evidence.root'));

        $this->assertNotFalse($publicRoot, 'root disk publik tidak ditemukan');
        $this->assertNotFalse($evidenceRoot, 'root disk evidence tidak ditemukan');

        $this->assertFalse(
            str_starts_with($evidenceRoot, $publicRoot),
            "Disk evidence ({$evidenceRoot}) berada DI DALAM disk publik, sehingga file masih bisa diakses lewat /storage."
        );
    }

    public function test_evidence_disk_has_no_public_url(): void
    {
        $this->assertArrayNotHasKey(
            'url',
            config('filesystems.disks.evidence'),
            'Disk evidence tidak boleh memiliki url karena file-nya privat.'
        );
    }

    public function test_guest_cannot_access_monitoring_photo(): void
    {
        $monitoring = WaterMonitoring::factory()->create(['foto' => 'monitoring/x.jpg']);

        $this->get(route('foto.monitoring', $monitoring))
            ->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_finance_photo(): void
    {
        $report = FinanceReport::factory()->create(['foto' => 'keuangan/x.jpg']);

        $this->get(route('foto.finance', $report))
            ->assertRedirect(route('login'));
    }

    /*
     |--------------------------------------------------------------------------
     | 2.1 - hak akses streaming
     |--------------------------------------------------------------------------
     */

    public function test_owner_can_view_own_monitoring_photo(): void
    {
        $petugas = User::factory()->petugas()->create();
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'foto' => 'monitoring/milik-sendiri.jpg',
            'foto_disk' => 'evidence',
        ]);
        Storage::disk('evidence')->put($monitoring->foto, 'konten-foto-rahasia');

        $response = $this->actingAs($petugas)->get(route('foto.monitoring', $monitoring));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertSame(
            'konten-foto-rahasia',
            file_get_contents($response->baseResponse->getFile()->getPathname())
        );
    }

    public function test_owner_can_view_own_finance_photo(): void
    {
        $petugas = User::factory()->petugas()->create();
        $report = FinanceReport::factory()->create([
            'user_id' => $petugas->id,
            'foto' => 'keuangan/milik-sendiri.jpg',
            'foto_disk' => 'evidence',
        ]);
        Storage::disk('evidence')->put($report->foto, 'konten-foto-keuangan');

        $this->actingAs($petugas)
            ->get(route('foto.finance', $report))
            ->assertOk();
    }

    public function test_petugas_cannot_view_other_users_monitoring_photo(): void
    {
        $owner = User::factory()->petugas()->create();
        $other = User::factory()->petugas()->create();
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $owner->id,
            'foto' => 'monitoring/orang-lain.jpg',
            'foto_disk' => 'evidence',
        ]);
        Storage::disk('evidence')->put($monitoring->foto, 'rahasia');

        $this->actingAs($other)
            ->get(route('foto.monitoring', $monitoring))
            ->assertForbidden();
    }

    public function test_petugas_cannot_view_other_users_finance_photo(): void
    {
        $owner = User::factory()->petugas()->create();
        $other = User::factory()->petugas()->create();
        $report = FinanceReport::factory()->create([
            'user_id' => $owner->id,
            'foto' => 'keuangan/orang-lain.jpg',
            'foto_disk' => 'evidence',
        ]);
        Storage::disk('evidence')->put($report->foto, 'rahasia');

        $this->actingAs($other)
            ->get(route('foto.finance', $report))
            ->assertForbidden();
    }

    public function test_admin_can_view_any_monitoring_photo(): void
    {
        $admin = User::factory()->admin()->create();
        $petugas = User::factory()->petugas()->create();
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'foto' => 'monitoring/admin-boleh.jpg',
            'foto_disk' => 'evidence',
        ]);
        Storage::disk('evidence')->put($monitoring->foto, 'konten');

        $this->actingAs($admin)
            ->get(route('foto.monitoring', $monitoring))
            ->assertOk();
    }

    /*
     |--------------------------------------------------------------------------
     | 2.1 - kasus tepi
     |--------------------------------------------------------------------------
     */

    public function test_photo_returns_404_when_file_is_missing(): void
    {
        $petugas = User::factory()->petugas()->create();
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'foto' => 'monitoring/tidak-ada.jpg',
            'foto_disk' => 'evidence',
        ]);

        $this->actingAs($petugas)
            ->get(route('foto.monitoring', $monitoring))
            ->assertNotFound();
    }

    public function test_photo_path_traversal_is_rejected(): void
    {
        $petugas = User::factory()->petugas()->create();
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'foto' => '../../../config/database.php',
            'foto_disk' => 'evidence',
        ]);

        $this->actingAs($petugas)
            ->get(route('foto.monitoring', $monitoring))
            ->assertNotFound();
    }

    public function test_legacy_photo_on_public_disk_is_still_served(): void
    {
        $petugas = User::factory()->petugas()->create();
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'foto' => 'monitoring/foto-lama.jpg',
        ]);
        Storage::disk('public')->put($monitoring->foto, 'konten-lama');

        $this->assertSame('public', $monitoring->fotoDiskName());

        $this->actingAs($petugas)
            ->get(route('foto.monitoring', $monitoring))
            ->assertOk();
    }

    /*
     |--------------------------------------------------------------------------
     | 2.2 - urutan hapus foto
     |--------------------------------------------------------------------------
     */

    public function test_old_photo_is_deleted_only_after_database_update_succeeds(): void
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
            'foto_disk' => 'evidence',
        ]);
        Storage::disk('evidence')->put('monitoring/lama.jpg', 'konten-lama');

        $this->actingAs($petugas)->put(route('monitoring.update', $monitoring), [
            'location_id' => $location->id,
            'tanggal' => '2026-09-01',
            'sesi' => 'pagi',
            'kondisi' => 'normal',
            'keterangan' => 'Foto diganti.',
            'foto' => UploadedFile::fake()->image('baru.jpg'),
        ])->assertRedirect(route('dashboard'));

        $fotoBaru = $monitoring->fresh()->foto;

        $this->assertNotSame('monitoring/lama.jpg', $fotoBaru);
        Storage::disk('evidence')->assertMissing('monitoring/lama.jpg');
        Storage::disk('evidence')->assertExists($fotoBaru);
        $this->assertSame('evidence', $monitoring->fresh()->foto_disk);
    }

    public function test_old_photo_survives_when_database_update_fails(): void
    {
        WaterMonitoring::updating(function () {
            throw new RuntimeException('simulasi kegagalan database');
        });

        $petugas = User::factory()->petugas()->create();
        $location = MonitoringLocation::factory()->create();
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'tanggal' => '2026-09-01',
            'sesi' => 'pagi',
            'waktu' => '08:00',
            'foto' => 'monitoring/lama.jpg',
            'foto_disk' => 'evidence',
        ]);
        Storage::disk('evidence')->put('monitoring/lama.jpg', 'konten-lama');

        $response = $this->actingAs($petugas)->put(route('monitoring.update', $monitoring), [
            'location_id' => $location->id,
            'tanggal' => '2026-09-01',
            'sesi' => 'pagi',
            'kondisi' => 'normal',
            'keterangan' => 'Foto diganti.',
            'foto' => UploadedFile::fake()->image('baru.jpg'),
        ]);

        $response->assertStatus(500);

        Storage::disk('evidence')->assertExists('monitoring/lama.jpg');

        $this->assertDatabaseHas('water_monitorings', [
            'id' => $monitoring->id,
            'foto' => 'monitoring/lama.jpg',
        ]);

        $this->assertSame(
            ['monitoring/lama.jpg'],
            Storage::disk('evidence')->allFiles(),
            'Hanya foto lama yang boleh tersisa. File baru yang gagal tersimpan harus dibersihkan.'
        );
    }

    public function test_replacing_legacy_public_photo_removes_the_public_copy(): void
    {
        $petugas = User::factory()->petugas()->create();
        $location = MonitoringLocation::factory()->create();
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'tanggal' => '2026-09-01',
            'sesi' => 'pagi',
            'waktu' => '08:00',
            'foto' => 'monitoring/lama-publik.jpg',
        ]);
        Storage::disk('public')->put('monitoring/lama-publik.jpg', 'konten-publik');

        $this->actingAs($petugas)->put(route('monitoring.update', $monitoring), [
            'location_id' => $location->id,
            'tanggal' => '2026-09-01',
            'sesi' => 'pagi',
            'kondisi' => 'normal',
            'keterangan' => 'Pindah ke disk privat.',
            'foto' => UploadedFile::fake()->image('baru.jpg'),
        ])->assertRedirect(route('dashboard'));

        $fotoBaru = $monitoring->fresh()->foto;

        Storage::disk('public')->assertMissing('monitoring/lama-publik.jpg');
        Storage::disk('evidence')->assertExists($fotoBaru);
        $this->assertSame('evidence', $monitoring->fresh()->foto_disk);
    }

    /*
     |--------------------------------------------------------------------------
     | 2.1 - hapus record ikut menghapus file di disk yang benar
     |--------------------------------------------------------------------------
     */

    public function test_destroy_removes_photo_from_the_correct_disk(): void
    {
        $petugas = User::factory()->petugas()->create();
        $location = MonitoringLocation::factory()->create();
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'foto' => 'monitoring/hapus.jpg',
            'foto_disk' => 'evidence',
        ]);
        Storage::disk('evidence')->put('monitoring/hapus.jpg', 'konten');

        $this->actingAs($petugas)
            ->delete(route('monitoring.destroy', $monitoring))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('water_monitorings', ['id' => $monitoring->id]);
        Storage::disk('evidence')->assertMissing('monitoring/hapus.jpg');
    }

    /*
     |--------------------------------------------------------------------------
     | 2.1 - halaman tidak boleh memuat URL foto publik /storage
     |--------------------------------------------------------------------------
     */

    public function test_monitoring_index_points_photo_to_the_private_route(): void
    {
        $admin = User::factory()->admin()->create();
        $monitoring = WaterMonitoring::factory()->create([
            'tanggal' => now()->toDateString(),
            'sesi' => 'pagi',
        ]);

        $response = $this->actingAs($admin)->get(route('monitoring.index'));

        $response->assertOk();
        $response->assertSee(
            'data-foto-src="'.route('foto.monitoring', $monitoring).'"',
            false
        );
        $response->assertDontSee('/storage/', false);
    }

    public function test_monitoring_show_points_photo_to_the_private_route(): void
    {
        $admin = User::factory()->admin()->create();
        $monitoring = WaterMonitoring::factory()->create(['foto' => 'monitoring/abc.jpg']);

        $response = $this->actingAs($admin)->get(route('monitoring.show', $monitoring));

        $response->assertOk();
        $response->assertSee(
            'src="'.route('foto.monitoring', $monitoring).'"',
            false
        );
        $response->assertDontSee('/storage/', false);
    }

    public function test_finance_riwayat_points_photo_to_the_private_route(): void
    {
        $petugas = User::factory()->petugas()->create();
        $report = FinanceReport::factory()->create([
            'user_id' => $petugas->id,
            'tanggal' => now()->toDateString(),
        ]);

        $response = $this->actingAs($petugas)->get(route('keuangan.riwayat'));

        $response->assertOk();
        $response->assertSee(
            'data-foto-src="'.route('foto.finance', $report).'"',
            false
        );
        $response->assertDontSee('/storage/', false);
    }
}
