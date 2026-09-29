<?php

namespace Tests\Feature;

use App\Models\FinanceLocation;
use App\Models\FinanceReport;
use App\Models\MonitoringLocation;
use App\Models\User;
use App\Models\WaterMonitoring;
use App\Support\PhotoStorage;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactoryContract;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use Mockery;
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

    public function test_photo_returns_404_when_file_disappears_between_check_and_stream(): void
    {
        // Mensimulasikan kondisi balapan: file masih ada saat pemeriksaan
        // keberadaan dilakukan, lalu hilang sebelum BinaryFileResponse
        // benar-benar mengirimkannya. Tanpa penanganan FileException di
        // PhotoController::stream(), kondisi ini berakhir sebagai 500.
        $petugas = User::factory()->petugas()->create();
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'foto' => 'monitoring/hilang-saat-kirim.jpg',
            'foto_disk' => 'evidence',
        ]);

        $disk = Mockery::mock(Storage::disk('evidence'))->makePartial();
        $disk->shouldReceive('exists')->once()->andReturn(true);
        $disk->shouldReceive('path')->andReturnUsing(fn ($path) => storage_path('app/private-evidence/'.$path));
        // Path menunjuk ke file yang memang tidak ada di disk sungguhan.
        Storage::fake('evidence');
        Storage::shouldReceive('disk')->with('evidence')->andReturn($disk);

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
        // Halaman detail menampilkan foto hanya bila filenya benar-benar ada,
        // jadi file-nya harus benar-benar dibuat di disk. Disk diambil dari
        // model supaya ikut mengikuti nilai foto_disk pada record.
        $monitoring->fotoDisk()->put($monitoring->foto, 'konten-foto');

        $response = $this->actingAs($admin)->get(route('monitoring.show', $monitoring));

        $response->assertOk();
        $response->assertSee(
            'src="'.route('foto.monitoring', $monitoring).'"',
            false
        );
        $response->assertDontSee('/storage/', false);
    }

    public function test_monitoring_show_hides_a_photo_whose_file_is_gone(): void
    {
        // Kolom foto masih terisi, tetapi file-nya sudah hilang dari disk.
        // Tanpa pemeriksaan keberadaan file, halaman ini mengirim <img> yang
        // berakhir sebagai gambar rusak. Yang benar adalah menampilkan
        // pesan "tidak ada foto" karena memang tidak ada yang bisa dibuka.
        $admin = User::factory()->admin()->create();
        $monitoring = WaterMonitoring::factory()->create(['foto' => 'monitoring/hilang.jpg']);

        $response = $this->actingAs($admin)->get(route('monitoring.show', $monitoring));

        $response->assertOk();
        $response->assertDontSee(
            'src="'.route('foto.monitoring', $monitoring).'"',
            false
        );
        $response->assertSee('Tidak ada foto untuk pemeriksaan ini.');
    }

    public function test_monitoring_edit_hides_a_photo_whose_file_is_gone(): void
    {
        $admin = User::factory()->admin()->create();
        $monitoring = WaterMonitoring::factory()->create(['foto' => 'monitoring/hilang.jpg']);

        $response = $this->actingAs($admin)->get(route('monitoring.edit', $monitoring));

        $response->assertOk();
        $response->assertDontSee(
            'src="'.route('foto.monitoring', $monitoring).'"',
            false
        );
    }

    public function test_finance_edit_hides_a_photo_whose_file_is_gone(): void
    {
        $admin = User::factory()->admin()->create();
        $report = FinanceReport::factory()->create([
            'user_id' => $admin->id,
            'foto' => 'keuangan/hilang.jpg',
        ]);

        $response = $this->actingAs($admin)->get(route('keuangan.edit', $report));

        $response->assertOk();
        $response->assertDontSee(
            'src="'.route('foto.finance', $report).'"',
            false
        );
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

    /*
     |--------------------------------------------------------------------------
     | 2.2 - destroy harus menghapus baris lebih dulu, baru file
     |--------------------------------------------------------------------------
     */

    public function test_photo_survives_when_the_monitoring_row_cannot_be_deleted(): void
    {
        WaterMonitoring::deleting(function () {
            throw new RuntimeException('simulasi kegagalan database');
        });

        $petugas = User::factory()->petugas()->create();
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'foto' => 'monitoring/jaga.jpg',
            'foto_disk' => 'evidence',
        ]);
        Storage::disk('evidence')->put('monitoring/jaga.jpg', 'konten-jaga');

        $this->actingAs($petugas)
            ->delete(route('monitoring.destroy', $monitoring))
            ->assertStatus(500);

        Storage::disk('evidence')->assertExists('monitoring/jaga.jpg');
        $this->assertDatabaseHas('water_monitorings', ['id' => $monitoring->id]);
    }

    public function test_photo_survives_when_the_finance_row_cannot_be_deleted(): void
    {
        FinanceReport::deleting(function () {
            throw new RuntimeException('simulasi kegagalan database');
        });

        $petugas = User::factory()->petugas()->create();
        $report = FinanceReport::factory()->create([
            'user_id' => $petugas->id,
            'foto' => 'keuangan/jaga.jpg',
            'foto_disk' => 'evidence',
        ]);
        Storage::disk('evidence')->put('keuangan/jaga.jpg', 'konten-jaga');

        $this->actingAs($petugas)
            ->delete(route('keuangan.destroy', $report))
            ->assertStatus(500);

        Storage::disk('evidence')->assertExists('keuangan/jaga.jpg');
        $this->assertDatabaseHas('finance_reports', ['id' => $report->id]);
    }

    /*
     |--------------------------------------------------------------------------
     | 2.3 - kegagalan tulis foto tidak boleh jadi halaman 500
     |--------------------------------------------------------------------------
     |
     | Disk 'evidence' dikonfigurasi 'throw' => true, jadi menulis file
     | ke sana bisa melempar exception saat storage penuh atau foldernya
     | tidak writable. Semua jalur yang memanggil store() harus
     | menerjemahkannya menjadi respons ramah, bukan halaman error.
     |
     */

    public function test_monitoring_store_reports_friendly_error_when_photo_cannot_be_written(): void
    {
        $this->breakEvidenceDiskWrite();

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

        $response->assertRedirect();
        $response->assertSessionHas('error', PhotoStorage::MESSAGE_GAGAL_SIMPAN);
        $response->assertSessionMissing('success');

        $this->assertDatabaseCount('water_monitorings', 0);
        $this->assertSame(
            [],
            Storage::disk('evidence')->allFiles(),
            'Tidak boleh ada file tersisa ketika penulisan gagal.'
        );
    }

    public function test_finance_store_reports_friendly_error_when_photo_cannot_be_written(): void
    {
        $this->breakEvidenceDiskWrite();

        $petugas = User::factory()->petugas()->create();
        $location = FinanceLocation::factory()->create();

        $response = $this->actingAs($petugas)->post(route('keuangan.store'), [
            'tanggal' => '2026-09-10',
            'location_id' => $location->id,
            'kondisi' => 'normal',
            'keterangan' => 'Kondisi normal bulan September.',
            'foto' => UploadedFile::fake()->image('foto.jpg'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', PhotoStorage::MESSAGE_GAGAL_SIMPAN);
        $response->assertSessionMissing('success');

        $this->assertDatabaseCount('finance_reports', 0);
        $this->assertSame(
            [],
            Storage::disk('evidence')->allFiles(),
            'Tidak boleh ada file tersisa ketika penulisan gagal.'
        );
    }

    public function test_store_reports_friendly_error_when_disk_returns_false_instead_of_throwing(): void
    {
        // Second branch dari penjaga di PhotoStorage::storeUploaded():
        // store() mengembalikan false, bukan melempar exception. Keduanya
        // harus menghasilkan respons yang sama.
        $this->breakEvidenceDiskWrite(throwInstead: false);

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

        $response->assertRedirect();
        $response->assertSessionHas('error', PhotoStorage::MESSAGE_GAGAL_SIMPAN);
        $this->assertDatabaseCount('water_monitorings', 0);
    }

    public function test_monitoring_update_keeps_old_photo_when_new_photo_cannot_be_written(): void
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

        $this->breakEvidenceDiskWrite();

        $response = $this->actingAs($petugas)->put(route('monitoring.update', $monitoring), [
            'location_id' => $location->id,
            'tanggal' => '2026-09-01',
            'sesi' => 'pagi',
            'kondisi' => 'normal',
            'keterangan' => 'Foto diganti.',
            'foto' => UploadedFile::fake()->image('baru.jpg'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', PhotoStorage::MESSAGE_GAGAL_SIMPAN);
        $response->assertSessionMissing('success');

        $this->assertDatabaseHas('water_monitorings', [
            'id' => $monitoring->id,
            'foto' => 'monitoring/lama.jpg',
            'foto_disk' => 'evidence',
        ]);

        $this->assertSame(
            ['monitoring/lama.jpg'],
            Storage::disk('evidence')->allFiles(),
            'Record tidak boleh berubah dan tidak boleh ada file baru yang tersisa.'
        );
        $this->assertSame(
            'konten-lama',
            Storage::disk('evidence')->get('monitoring/lama.jpg'),
            'Foto lama harus tetap utuh, bukan tergantikan file kosong.'
        );
    }

    public function test_finance_update_keeps_old_photo_when_new_photo_cannot_be_written(): void
    {
        $petugas = User::factory()->petugas()->create();
        $location = FinanceLocation::factory()->create();
        $report = FinanceReport::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'tanggal' => '2026-09-10',
            'foto' => 'keuangan/lama.jpg',
            'foto_disk' => 'evidence',
        ]);
        Storage::disk('evidence')->put('keuangan/lama.jpg', 'konten-lama');

        $this->breakEvidenceDiskWrite();

        $response = $this->actingAs($petugas)->put(route('keuangan.update', $report), [
            'tanggal' => '2026-09-10',
            'location_id' => $location->id,
            'kondisi' => 'normal',
            'keterangan' => 'Foto diganti.',
            'foto' => UploadedFile::fake()->image('baru.jpg'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', PhotoStorage::MESSAGE_GAGAL_SIMPAN);
        $response->assertSessionMissing('success');

        $this->assertDatabaseHas('finance_reports', [
            'id' => $report->id,
            'foto' => 'keuangan/lama.jpg',
            'foto_disk' => 'evidence',
        ]);

        $this->assertSame(
            ['keuangan/lama.jpg'],
            Storage::disk('evidence')->allFiles(),
            'Record tidak boleh berubah dan tidak boleh ada file baru yang tersisa.'
        );
    }

    /*
     |--------------------------------------------------------------------------
     | 2.3 - kegagalan hapus file setelah baris terhapus
     |--------------------------------------------------------------------------
     |
     | Pada destroy(), baris database dihapus lebih dulu dan file tinggal
     | dibersihkan. Kalau delete() melempar, pengguna akan melihat 500
     | padahal hapus datanya sendiri sudah berhasil.
     |
     */

    public function test_destroy_finance_removes_photo_from_the_evidence_disk(): void
    {
        $petugas = User::factory()->petugas()->create();
        $report = FinanceReport::factory()->create([
            'user_id' => $petugas->id,
            'foto' => 'keuangan/hapus.jpg',
            'foto_disk' => 'evidence',
        ]);
        Storage::disk('evidence')->put('keuangan/hapus.jpg', 'konten');

        $this->actingAs($petugas)
            ->delete(route('keuangan.destroy', $report))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('finance_reports', ['id' => $report->id]);
        Storage::disk('evidence')->assertMissing('keuangan/hapus.jpg');
    }

    public function test_finance_destroy_succeeds_when_photo_file_cannot_be_deleted(): void
    {
        $petugas = User::factory()->petugas()->create();
        $report = FinanceReport::factory()->create([
            'user_id' => $petugas->id,
            'foto' => 'keuangan/kunci.jpg',
            'foto_disk' => 'evidence',
        ]);
        Storage::disk('evidence')->put('keuangan/kunci.jpg', 'konten');

        $evidence = $this->breakEvidenceDiskDelete('keuangan/kunci.jpg');

        $this->actingAs($petugas)
            ->delete(route('keuangan.destroy', $report))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('finance_reports', ['id' => $report->id]);
        $evidence->assertExists('keuangan/kunci.jpg');
    }

    public function test_monitoring_destroy_succeeds_when_photo_file_cannot_be_deleted(): void
    {
        $petugas = User::factory()->petugas()->create();
        $location = MonitoringLocation::factory()->create();
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'foto' => 'monitoring/kunci.jpg',
            'foto_disk' => 'evidence',
        ]);
        Storage::disk('evidence')->put('monitoring/kunci.jpg', 'konten');

        $evidence = $this->breakEvidenceDiskDelete('monitoring/kunci.jpg');

        $this->actingAs($petugas)
            ->delete(route('monitoring.destroy', $monitoring))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('water_monitorings', ['id' => $monitoring->id]);
        $evidence->assertExists('monitoring/kunci.jpg');
    }

    /**
     * Ganti disk evidence dengan tiruan yang putFileAs()-nya gagal.
     *
     * Storage::fake() memakai filesystem in-memory yang tidak pernah gagal
     * menulis, jadi kegagalan hanya bisa ditiru lewat mock.
     *
     * Yang diganti adalah binding 'filesystem', bukan facade Storage.
     * UploadedFile::store() me-resolve disk lewat contract
     * Illuminate\Contracts\Filesystem\Factory yang di-alias ke 'filesystem',
     * jadi mocking facade Storage saja tidak akan tersentuh. Storage::swap()
     * diperlukan karena ia menimpa cache facade sekaligus isi container.
     */
    protected function breakEvidenceDiskWrite(bool $throwInstead = true): void
    {
        $manager = app('filesystem');
        $mock = Mockery::mock(Storage::disk('evidence'))->makePartial();

        if ($throwInstead) {
            $mock->shouldReceive('putFileAs')->andThrow(
                UnableToWriteFile::atLocation('foto-bukti.jpg', 'storage tidak writable')
            );
        } else {
            $mock->shouldReceive('putFileAs')->andReturnFalse();
        }

        $factory = Mockery::mock(FilesystemFactoryContract::class);
        $factory->shouldReceive('disk')->andReturnUsing(
            static fn ($name = null) => $name === PhotoStorage::EVIDENCE ? $mock : $manager->disk($name)
        );

        Storage::swap($factory);
    }

    /**
     * Ganti disk evidence dengan tiruan yang delete()-nya melempar.
     *
     * Cara yang dipakai untuk memastikan pembungkus best-effort bekerja:
     * tanpa itu, delete() mentah akan mengubah operasi yang sukses
     * menjadi halaman 500.
     *
     * Mengembalikan disk evidence asli untuk assertion, karena disk yang
     * dikembalikan helper ini sudah tidak bisa dipakai setelah facade
     * Storage di-swap.
     */
    protected function breakEvidenceDiskDelete(string $path): object
    {
        $manager = app('filesystem');
        $evidence = Storage::disk('evidence');
        $mock = Mockery::mock($evidence)->makePartial();

        $mock->shouldReceive('delete')
            ->andThrow(UnableToDeleteFile::atLocation($path, 'file terkunci'));

        $factory = Mockery::mock(FilesystemFactoryContract::class);
        $factory->shouldReceive('disk')->andReturnUsing(
            static fn ($name = null) => $name === PhotoStorage::EVIDENCE ? $mock : $manager->disk($name)
        );

        Storage::swap($factory);

        return $evidence;
    }

    /*
     |--------------------------------------------------------------------------
     | 2.7 - verifikasi salinan sebelum file asli dihapus
     |--------------------------------------------------------------------------
     |
     | Perintah rehome memakai isVerifiedCopy() untuk memutuskan apakah
     | file di disk privat boleh dipercayai. Kalau helper ini terlalu(longgar,
     | foto yang terpotong akanDianggap utuh dan file publik yang masih
     | benar bisa terhapus.
     |
     */

    public function test_is_verified_copy_accepts_identical_content(): void
    {
        Storage::disk('public')->put('monitoring/sama.jpg', 'isi-identik');
        Storage::disk('evidence')->put('monitoring/sama.jpg', 'isi-identik');

        $this->assertTrue(PhotoStorage::isVerifiedCopy(
            Storage::disk('public'),
            'monitoring/sama.jpg',
            Storage::disk('evidence'),
            'monitoring/sama.jpg'
        ));
    }

    public function test_is_verified_copy_accepts_identical_empty_files(): void
    {
        // Dua file kosong tetap salinan yang sah. Menolaknya akan membuat
        // foto yang benar-benar kosong selalu dilaporkan sebagai bentrok.
        Storage::disk('public')->put('monitoring/kosong.jpg', '');
        Storage::disk('evidence')->put('monitoring/kosong.jpg', '');

        $this->assertTrue(PhotoStorage::isVerifiedCopy(
            Storage::disk('public'),
            'monitoring/kosong.jpg',
            Storage::disk('evidence'),
            'monitoring/kosong.jpg'
        ));
    }

    public function test_is_verified_copy_rejects_truncated_content(): void
    {
        // Kasus yang paling berbahaya: salinan privat terputus di tengah,
        // jadi ada tapi isinya tidak lengkap.
        Storage::disk('public')->put('monitoring/terpotong.jpg', str_repeat('a', 4096));
        Storage::disk('evidence')->put('monitoring/terpotong.jpg', str_repeat('a', 16));

        $this->assertFalse(PhotoStorage::isVerifiedCopy(
            Storage::disk('public'),
            'monitoring/terpotong.jpg',
            Storage::disk('evidence'),
            'monitoring/terpotong.jpg'
        ));
    }

    public function test_is_verified_copy_rejects_different_content_of_the_same_size(): void
    {
        // Panjang keduanya 9 byte. Yang menangkapnya hanya sha256; kalau
        // membandingkan ukuran saja, file ini akan salah dianggap salinan
        // utuh dan file publik yang benar bisa ikut terhapus.
        $this->assertSame(9, strlen('versi-AAA'));
        $this->assertSame(9, strlen('versi-BBB'));

        Storage::disk('public')->put('monitoring/beda.jpg', 'versi-AAA');
        Storage::disk('evidence')->put('monitoring/beda.jpg', 'versi-BBB');

        $this->assertFalse(PhotoStorage::isVerifiedCopy(
            Storage::disk('public'),
            'monitoring/beda.jpg',
            Storage::disk('evidence'),
            'monitoring/beda.jpg'
        ));
    }

    public function test_is_verified_copy_returns_false_when_file_is_missing(): void
    {
        Storage::disk('public')->put('monitoring/ada.jpg', 'isi');

        $this->assertFalse(PhotoStorage::isVerifiedCopy(
            Storage::disk('public'),
            'monitoring/ada.jpg',
            Storage::disk('evidence'),
            'monitoring/tidak-ada.jpg'
        ));
    }

    public function test_is_verified_copy_returns_false_when_size_check_fails(): void
    {
        Storage::disk('public')->put('monitoring/ukuran.jpg', 'isi');
        Storage::disk('evidence')->put('monitoring/ukuran.jpg', 'isi');

        $evidence = $this->breakDiskCall(
            Storage::disk('evidence'),
            'size',
            UnableToRetrieveMetadata::fileSize('monitoring/ukuran.jpg', 'metadata tidak terbaca')
        );

        $this->assertFalse(PhotoStorage::isVerifiedCopy(
            Storage::disk('public'),
            'monitoring/ukuran.jpg',
            $evidence,
            'monitoring/ukuran.jpg'
        ));
    }

    public function test_is_verified_copy_returns_false_when_read_fails_after_size_matched(): void
    {
        // Ukuran cocok lalu proses berhenti sebelum hashing selesai. Hasil
        // tetap harus "tidak terverifikasi", bukan "terverifikasi".
        Storage::disk('public')->put('monitoring/baca.jpg', 'isi-sama');
        Storage::disk('evidence')->put('monitoring/baca.jpg', 'isi-sama');

        $evidence = $this->breakDiskCall(
            Storage::disk('evidence'),
            'get',
            UnableToReadFile::fromLocation('monitoring/baca.jpg', 'permission berubah')
        );

        $this->assertFalse(PhotoStorage::isVerifiedCopy(
            Storage::disk('public'),
            'monitoring/baca.jpg',
            $evidence,
            'monitoring/baca.jpg'
        ));
    }

    /**
     * Tiruan disk dengan satu metode yang melempar exception.
     *
     * Storage::fake() memakai filesystem in-memory yang andal, jadi kegagalan
     * hanya bisa ditiru lewat mock. Metode lain tetap diteruskan ke disk
     * asli supaya perbandingan size/get pada jalur yang tidak dituju mock
     * masih berperilaku realistis.
     */
    protected function breakDiskCall(Filesystem $disk, string $method, RuntimeException|UnableToReadFile|UnableToRetrieveMetadata $exception): Filesystem
    {
        $mock = Mockery::mock($disk)->makePartial();
        $mock->shouldReceive($method)->andThrow($exception);

        return $mock;
    }
}
