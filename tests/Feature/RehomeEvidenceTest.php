<?php

namespace Tests\Feature;

use App\Console\Commands\RehomeEvidence;
use App\Models\FinanceReport;
use App\Models\User;
use App\Models\WaterMonitoring;
use App\Support\PhotoStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToDeleteFile;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class RehomeEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('evidence');
    }

    public function test_dry_run_does_not_change_anything(): void
    {
        $monitoring = $this->makeLegacyMonitoring('monitoring/lama.jpg', 'konten-lama');

        $this->artisan('evidence:rehome')->assertSuccessful();

        Storage::disk('public')->assertExists('monitoring/lama.jpg');
        Storage::disk('evidence')->assertMissing('monitoring/lama.jpg');
        $this->assertSame('public', $monitoring->fresh()->foto_disk);
    }

    public function test_apply_moves_file_and_updates_database(): void
    {
        $monitoring = $this->makeLegacyMonitoring('monitoring/lama.jpg', 'konten-lama');

        $this->artisan('evidence:rehome', ['--apply' => true])->assertSuccessful();

        Storage::disk('public')->assertMissing('monitoring/lama.jpg');
        Storage::disk('evidence')->assertExists('monitoring/lama.jpg');
        $this->assertSame('konten-lama', Storage::disk('evidence')->get('monitoring/lama.jpg'));
        $this->assertSame('evidence', $monitoring->fresh()->foto_disk);
    }

    public function test_apply_also_handles_finance_reports(): void
    {
        $report = FinanceReport::factory()->create([
            'foto' => 'keuangan/lama.jpg',
            'foto_disk' => 'public',
        ]);
        Storage::disk('public')->put('keuangan/lama.jpg', 'konten-keuangan');

        $this->artisan('evidence:rehome', ['--apply' => true])->assertSuccessful();

        Storage::disk('public')->assertMissing('keuangan/lama.jpg');
        Storage::disk('evidence')->assertExists('keuangan/lama.jpg');
        $this->assertSame('evidence', $report->fresh()->foto_disk);
    }

    public function test_apply_skips_records_already_on_private_disk(): void
    {
        $monitoring = WaterMonitoring::factory()->create([
            'foto' => 'monitoring/sudah.jpg',
            'foto_disk' => 'evidence',
        ]);
        Storage::disk('evidence')->put('monitoring/sudah.jpg', 'konten');

        $this->artisan('evidence:rehome', ['--apply' => true])->assertSuccessful();

        $this->assertSame('evidence', $monitoring->fresh()->foto_disk);
        Storage::disk('evidence')->assertExists('monitoring/sudah.jpg');
    }

    public function test_apply_skips_records_whose_file_is_missing_and_reports_failure(): void
    {
        $this->makeLegacyMonitoring('monitoring/hilang.jpg', null);

        $this->artisan('evidence:rehome', ['--apply' => true])
            ->expectsOutputToContain('tidak ada di disk publik')
            ->assertFailed();
    }

    public function test_apply_rejects_unsafe_path(): void
    {
        // Sengaja tanpa file: path '../../../config/database.php' akan ditolak
        // PhotoStorage::safePath() sebelum menyentuh disk.
        $this->makeLegacyMonitoring('../../config/database.php', null);

        $this->artisan('evidence:rehome', ['--apply' => true])
            ->expectsOutputToContain('tidak valid')
            ->assertFailed();
    }

    public function test_apply_reports_an_invalid_path_on_a_rehomed_record(): void
    {
        // Kolomnya sudah menunjuk ke disk privat tapi path-nya rusak. Record
        // seperti ini sebelumnya dilewati tanpa dihitung, sehingga laporan
        // menyatakan nol masalah padahal operator sama sekali tidak bisa
        // membuka fotonya. Path rusak harus dihitung sama persis seperti
        // path rusak pada record yang belum dipindah.
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => User::factory()->petugas()->create()->id,
            'foto' => '../../../config/database.php',
            'foto_disk' => 'evidence',
        ]);

        $this->artisan('evidence:rehome', ['--apply' => true])
            ->expectsOutputToContain('path foto tidak valid')
            ->expectsOutputToContain('gagal / tidak valid             : 1')
            ->assertFailed();

        // Perintah tidak boleh ikut menyentuh kolomnya.
        $this->assertSame('evidence', $monitoring->fresh()->foto_disk);
    }

    public function test_apply_leaves_public_copy_when_target_already_exists(): void
    {
        $monitoring = $this->makeLegacyMonitoring('monitoring/bentrok.jpg', 'versi-lama');
        Storage::disk('evidence')->put('monitoring/bentrok.jpg', 'versi-baru');

        $this->artisan('evidence:rehome', ['--apply' => true])->assertFailed();

        $this->assertSame('public', $monitoring->fresh()->foto_disk);
        Storage::disk('public')->assertExists('monitoring/bentrok.jpg');
        $this->assertSame('versi-baru', Storage::disk('evidence')->get('monitoring/bentrok.jpg'));
    }

    public function test_apply_reports_failure_when_public_file_cannot_be_deleted(): void
    {
        $monitoring = $this->makeLegacyMonitoring('monitoring/kunci.jpg', 'konten');
        [$public, $evidence] = $this->breakPublicDiskDelete(throwInstead: false);

        $this->artisan('evidence:rehome', ['--apply' => true])
            ->expectsOutputToContain('masih terekspos')
            ->assertFailed();

        $this->assertSame('evidence', $monitoring->fresh()->foto_disk);
        $evidence->assertExists('monitoring/kunci.jpg');
        $public->assertExists('monitoring/kunci.jpg');
    }

    public function test_apply_reports_failure_when_public_disk_throws_on_delete(): void
    {
        $monitoring = $this->makeLegacyMonitoring('monitoring/kunci2.jpg', 'konten');
        [$public, $evidence] = $this->breakPublicDiskDelete(throwInstead: true);

        $this->artisan('evidence:rehome', ['--apply' => true])
            ->expectsOutputToContain('masih terekspos')
            ->assertFailed();

        $this->assertSame('evidence', $monitoring->fresh()->foto_disk);
        $evidence->assertExists('monitoring/kunci2.jpg');
        $public->assertExists('monitoring/kunci2.jpg');
    }

    /*
     |--------------------------------------------------------------------------
     | Pemulihan setelah eksekusi terputus
     |--------------------------------------------------------------------------
     |
     | Perintah ini boleh dijalankan berulang kali, jadi harus aman dari
     | keadaan sisa. Dua jendela crash ditutup di sini:
     |
     | A. proses mati setelah menyalin ke disk privat, sebelum update
     |    database  -> kolom masih 'public' padahal salinan sudah ada.
     | B. proses mati setelah update database, sebelum menghapus file lama
     |    -> kolom sudah 'evidence' tapi foto masih terekspos lewat /storage.
     |
     | Jendela B inilah yang paling berbahaya: record seperti itu tidak
     | pernah ikut diambil pada pemrosesan normal, jadi dahulunya perintah
     | keluar dengan kode SUCCESS tanpa pernah memeriksa file publiknya.
     |
     */

    public function test_apply_removes_public_leftover_from_an_interrupted_run(): void
    {
        // Jendela crash B: kolom sudah menunjuk ke disk privat, file lama
        // di disk publik belum sempat dihapus.
        $monitoring = $this->makeRehomedMonitoring('monitoring/sisa.jpg', 'konten-asli', 'konten-asli');

        $this->artisan('evidence:rehome', ['--apply' => true])
            ->expectsOutputToContain('duplikat di disk publik dihapus')
            ->assertSuccessful();

        Storage::disk('public')->assertMissing('monitoring/sisa.jpg');
        Storage::disk('evidence')->assertExists('monitoring/sisa.jpg');
        $this->assertSame('evidence', $monitoring->fresh()->foto_disk);
    }

    public function test_apply_keeps_public_file_when_the_private_copy_is_missing(): void
    {
        // Kolom menunjuk ke disk privat tapi salinannya hilang. File di disk
        // publik sekarang satu-satunya salinan yang ada, jadi menghapusnya
        // akan membuang satu-satunya bukti.
        $monitoring = $this->makeRehomedMonitoring('monitoring/hanya-publik.jpg', 'satu-satunya', null);

        $this->artisan('evidence:rehome', ['--apply' => true])
            ->expectsOutputToContain('tidak ada di sana; file yang masih ada di disk publik dipertahankan sebagai satu-satunya salinan')
            ->assertFailed();

        Storage::disk('public')->assertExists('monitoring/hanya-publik.jpg');
        $this->assertSame('satu-satunya', Storage::disk('public')->get('monitoring/hanya-publik.jpg'));
        $this->assertSame('evidence', $monitoring->fresh()->foto_disk);
    }

    public function test_apply_keeps_both_files_when_the_private_copy_differs(): void
    {
        $monitoring = $this->makeRehomedMonitoring('monitoring/beda.jpg', 'versi-publik', 'versi-privat');

        $this->artisan('evidence:rehome', ['--apply' => true])
            ->expectsOutputToContain('tidak dapat dipastikan sama')
            ->assertFailed();

        Storage::disk('public')->assertExists('monitoring/beda.jpg');
        $this->assertSame('versi-publik', Storage::disk('public')->get('monitoring/beda.jpg'));
        $this->assertSame('versi-privat', Storage::disk('evidence')->get('monitoring/beda.jpg'));
        $this->assertSame('evidence', $monitoring->fresh()->foto_disk);
    }

    public function test_apply_reports_a_leftover_that_cannot_be_deleted(): void
    {
        $this->makeRehomedMonitoring('monitoring/kunci3.jpg', 'konten', 'konten');
        $this->breakPublicDiskDelete(throwInstead: true, path: 'monitoring/kunci3.jpg');

        $this->artisan('evidence:rehome', ['--apply' => true])
            ->expectsOutputToContain('masih terekspos di disk publik')
            ->assertFailed();

        Storage::disk('public')->assertExists('monitoring/kunci3.jpg');
    }

    public function test_apply_resumes_when_the_private_copy_is_verified_identical(): void
    {
        // Jendela crash A: salinan privat sudah ada dan utuh, kolom belum
        // pernah di-update.
        $isi = str_repeat('a', 4096);
        $monitoring = $this->makeLegacyMonitoring('monitoring/setengah.jpg', $isi);
        Storage::disk('evidence')->put('monitoring/setengah.jpg', $isi);

        $this->artisan('evidence:rehome', ['--apply' => true])
            ->expectsOutputToContain('sudah sama persis')
            ->expectsOutputToContain('dipulihkan')
            ->assertSuccessful();

        $this->assertSame('evidence', $monitoring->fresh()->foto_disk);
        Storage::disk('public')->assertMissing('monitoring/setengah.jpg');
        Storage::disk('evidence')->assertExists('monitoring/setengah.jpg');
    }

    public function test_apply_refuses_to_trust_a_truncated_private_copy(): void
    {
        // Salinan privat terputus di tengah: hanya 16 byte dari 4096. Kalau
        // dipercayai, perintah akan menunjuk kolom ke cuplikan itu lalu
        // MENGHAPUS salinan publik yang masih utuh — bukti yang baik hilang
        // dan yang tersisa tidak bisa dipakai. Test ini mengunci kewajiban
        // tersebut, jadi jangan longgarkan verifikasinya.
        $isiUtuh = str_repeat('a', 4096);
        $monitoring = $this->makeLegacyMonitoring('monitoring/terpotong.jpg', $isiUtuh);
        Storage::disk('evidence')->put('monitoring/terpotong.jpg', substr($isiUtuh, 0, 16));

        $this->artisan('evidence:rehome', ['--apply' => true])
            ->expectsOutputToContain('isinya berbeda dari disk publik, kedua file dipertahankan dan dilewati. Periksa manual')
            ->assertFailed();

        $this->assertSame('public', $monitoring->fresh()->foto_disk);
        Storage::disk('public')->assertExists('monitoring/terpotong.jpg');
        $this->assertSame($isiUtuh, Storage::disk('public')->get('monitoring/terpotong.jpg'));
        $this->assertSame(
            substr($isiUtuh, 0, 16),
            Storage::disk('evidence')->get('monitoring/terpotong.jpg'),
            'Salinan privat yang tidak terverifikasi tidak boleh ditimpa atau dihapus.'
        );
    }

    /*
     |--------------------------------------------------------------------------
     | moveOne() tidak boleh menghapus file publik sebelum salinannya utuh
     |--------------------------------------------------------------------------
     |
     | Test di bawah mengunci kewajiban verifikasi pada jalur ini.
     |
     | Berbeda dengan jalur pemulihan lain, jalur moveOne() MENYALIN file
     | itu sendiri, sehingga bisa keliru menganggap salinannya berhasil.
     | Yang paling merusak: put() mengembalikan berhasil untuk file yang
     | hanya berisi sebagian isi karena penulisan terputus di tengah.
     | Database lalu menunjuk ke salinan terpotong sementara file publik
     | yang utuh ikut terhapus, jadi foto yang tersisa hanya cuplikan
     | tidak berguna dan tidak ada error yang terlihat operator.
     |
     | Karena itu isVerifiedCopy() pada jalur moveOne() bukan sekadar
     | pemeriksaan tambahan: tanpa itu, kegagalan di bawah menghapus data
     | tanpa satu pun error yang terlihat operator.
     |
     */

    public function test_apply_keeps_public_file_when_source_read_returns_null(): void
    {
        // Disk 'public' ber-'throw' => false, jadi file yang tidak terbaca
        // menghasilkan null, bukan exception. Kegagalan ini tidak boleh lolos
        // tanpa penjelasan, dan tidak boleh meninggalkan file kosong di disk
        // privat yang membuat operator mengira foto sudah aman.
        $isi = str_repeat('a', 4096);
        $monitoring = $this->makeLegacyMonitoring('monitoring/tak-terbaca.jpg', $isi);
        // Disk asli dikembalikan supaya assertion di bawah membaca file
        // sungguhan, bukan tiruan yang get()-nya sudah di-intercept.
        [$public] = $this->breakPublicDiskRead('monitoring/tak-terbaca.jpg', returnsNull: true);

        $this->artisan('evidence:rehome', ['--apply' => true])
            ->expectsOutputToContain('tidak dapat dibaca dari disk publik')
            ->assertFailed();

        // File publik adalah satu-satunya salinan utuh dan tidak boleh hilang.
        $public->assertExists('monitoring/tak-terbaca.jpg');
        $this->assertSame($isi, $public->get('monitoring/tak-terbaca.jpg'));
        $this->assertSame('public', $monitoring->fresh()->foto_disk);

        // Dan tidak boleh ada file kosong yang menyesatkan di disk privat.
        Storage::disk('evidence')->assertMissing('monitoring/tak-terbaca.jpg');
    }

    public function test_apply_keeps_public_file_when_private_copy_is_truncated_while_writing(): void
    {
        // INI kasus yang merusak data. Penulisan terputus di tengah: put()
        // tidak melempar, file hanya berisi 16 byte dari 4096. Tanpa
        // verifikasi, database di-update ke cuplikan itu lalu file publik
        // yang utuh dihapus permanen.
        $isi = str_repeat('b', 4096);
        $monitoring = $this->makeLegacyMonitoring('monitoring/terputus-saat-tulis.jpg', $isi);
        $this->truncateEvidencePut('monitoring/terputus-saat-tulis.jpg', keepBytes: 16);

        $this->artisan('evidence:rehome', ['--apply' => true])
            ->expectsOutputToContain('tidak dapat dipastikan utuh')
            ->assertFailed();

        Storage::disk('public')->assertExists('monitoring/terputus-saat-tulis.jpg');
        $this->assertSame($isi, Storage::disk('public')->get('monitoring/terputus-saat-tulis.jpg'));
        $this->assertSame('public', $monitoring->fresh()->foto_disk);

        // Salinan terpotong tidak boleh ditinggalkan begitu saja: operator
        // akan mengira foto sudah aman di disk privat.
        Storage::disk('evidence')->assertMissing('monitoring/terputus-saat-tulis.jpg');
    }

    public function test_apply_reports_leftover_when_unverifiable_copy_cannot_be_removed(): void
    {
        // Salinan terpotong yang juga tidak bisa dihapus harus dilaporkan.
        // Foto publik tetap utuh, tapi sekarang ada file rusak di disk privat
        // yang wajib diketahui operator.
        //
        // Catatan: assertion output di sini sengaja memakai potongan pendek.
        // Pesan lengkapnya melewati lebar terminal, sehingga OutputFormatter
        // memanggil doWrite() berkeppot dan str_contains() di PendingCommand
        // bisa gagal kalau kata yang dicari tepat di batas potongan.
        $isi = str_repeat('c', 4096);
        $monitoring = $this->makeLegacyMonitoring('monitoring/sisa-terpotong.jpg', $isi);

        $this->breakEvidenceCopy('monitoring/sisa-terpotong.jpg', keepBytes: 16, undeletable: true);

        $this->artisan('evidence:rehome', ['--apply' => true])
            ->expectsOutputToContain('tidak dapat dipastikan utuh')
            ->expectsOutputToContain('tidak bisa dihapus')
            ->assertFailed();

        // Bukti utuh di disk publik harus tetap utuh dan tidak boleh diubah.
        Storage::disk('public')->assertExists('monitoring/sisa-terpotong.jpg');
        $this->assertSame($isi, Storage::disk('public')->get('monitoring/sisa-terpotong.jpg'));
        $this->assertSame('public', $monitoring->fresh()->foto_disk);

        // Sisa terpotong boleh tinggal, tapi hanya karena tidak bisa dihapus.
        // Kalau hilang diam-diam, operator akan menyangka semua beres.
        Storage::disk('evidence')->assertExists('monitoring/sisa-terpotong.jpg');
        $this->assertSame(16, Storage::disk('evidence')->size('monitoring/sisa-terpotong.jpg'));
    }

    /*
     |--------------------------------------------------------------------------
     | Konfigurasi throw pada kedua disk
     |--------------------------------------------------------------------------
     |
     | 'throw' => true pada disk 'evidence' adalah penjaga seluruh pemanggil
     | yang memeriksa nilai balik boolean. Tanpa itu put() dan delete() hanya
     | mengembalikan false saat disk penuh atau folder tidak writable, dan
     | pemanggil bisa keliru menganggap file sudah aman lalu membuang aslinya.
     |
     | Storage::fake() TIDAK mewarisi setelan ini — ia hanya menyet root (lihat
     | Illuminate\Support\Facades\Storage::fake()). Karena itu nilainya harus
     | dibaca langsung dari config supaya perubahan tidak lolos unnoticed.
     |
     */

    public function test_evidence_disk_is_configured_to_raise_on_failure(): void
    {
        $this->assertTrue(
            config('filesystems.disks.evidence.throw'),
            "Disk 'evidence' wajib 'throw' => true. Pemanggil seperti storeUploaded() dan hapusSalinanPrivat() bergantung pada exception untuk mendeteksi kegagalan."
        );
    }

    public function test_public_disk_stays_best_effort_so_legacy_photos_keep_reading(): void
    {
        // Sebaliknya, disk 'public' harus tetap throw => false. Foto lama
        // yang memang hilang harus menghasilkan 404 atau dilewati diam-diam,
        // bukan halaman error 500.
        $this->assertFalse(
            config('filesystems.disks.public.throw'),
            "Disk 'public' tidak boleh 'throw' => true supaya file legacy yang hilang tidak menjadi error 500."
        );
    }

    public function test_evidence_disk_root_is_outside_the_public_symlink(): void
    {
        // Root disk privat tidak boleh berada di dalam storage/app/public,
        // karena folder itu tersaji lewat symlink public/storage. Kalau rootnya
        // geser ke sana, seluruh foto bukti bisa diakses tanpa login.
        $root = config('filesystems.disks.evidence.root');
        $publicRoot = config('filesystems.disks.public.root');

        $this->assertNotSame($publicRoot, $root, 'Disk privat tidak boleh berbagi root dengan disk publik.');
        $this->assertStringNotContainsString(
            trim(str_replace('\\', '/', $publicRoot), '/').'/',
            trim(str_replace('\\', '/', (string) $root), '/'),
            'Root disk privat berada di dalam disk publik sehingga foto bukti bisa diakses lewat /storage.'
        );
    }

    public function test_apply_repairs_column_when_only_the_private_copy_exists(): void
    {
        // Jalur publik sudah terhapus sementara kolom belum pernah ter-update,
        // misalnya karena path-nya dipakai record lain. Yang rusak hanya kolomnya.
        $monitoring = $this->makeLegacyMonitoring('monitoring/yatim.jpg', null);
        Storage::disk('evidence')->put('monitoring/yatim.jpg', 'isi-yang-hidup');

        $this->artisan('evidence:rehome', ['--apply' => true])
            ->expectsOutputToContain('kolom foto_disk diperbaiki')
            ->assertSuccessful();

        $this->assertSame('evidence', $monitoring->fresh()->foto_disk);
        Storage::disk('evidence')->assertExists('monitoring/yatim.jpg');
    }

    public function test_dry_run_reports_leftovers_without_changing_anything(): void
    {
        $sisa = $this->makeRehomedMonitoring('monitoring/sisa-a.jpg', 'sama', 'sama');

        $isi = str_repeat('b', 2048);
        $setengah = $this->makeLegacyMonitoring('monitoring/setengah-b.jpg', $isi);
        Storage::disk('evidence')->put('monitoring/setengah-b.jpg', $isi);

        $this->artisan('evidence:rehome')
            ->expectsOutputToContain('akan dihapus dari disk publik')
            ->expectsOutputToContain('akan dipulihkan')
            ->assertSuccessful();

        // Tidak boleh ada yang berubah di dry-run.
        Storage::disk('public')->assertExists('monitoring/sisa-a.jpg');
        Storage::disk('public')->assertExists('monitoring/setengah-b.jpg');
        $this->assertSame('evidence', $sisa->fresh()->foto_disk);
        $this->assertSame('public', $setengah->fresh()->foto_disk);
    }

    public function test_apply_can_be_run_repeatedly_without_side_effects(): void
    {
        $this->makeLegacyMonitoring('monitoring/a.jpg', 'isi-a');
        $this->makeLegacyMonitoring('monitoring/b.jpg', 'isi-b');
        $sisa = $this->makeRehomedMonitoring('monitoring/c.jpg', 'isi-c', 'isi-c');

        $this->artisan('evidence:rehome', ['--apply' => true])->assertSuccessful();
        // Jalankan lagi: semua file sudah beres, jadi tidak ada yang boleh
        // diubah dan tidak boleh ada yang dilaporkan perlu tindakan.
        $this->artisan('evidence:rehome', ['--apply' => true])->assertSuccessful();

        foreach (['a', 'b', 'c'] as $nama) {
            Storage::disk('public')->assertMissing("monitoring/{$nama}.jpg");
            Storage::disk('evidence')->assertExists("monitoring/{$nama}.jpg");
        }

        $this->assertSame(0, WaterMonitoring::where('foto_disk', '!=', 'evidence')->count());
        $this->assertSame('evidence', $sisa->fresh()->foto_disk);
    }

    public function test_apply_processes_every_record_across_chunk_boundaries(): void
    {
        // Record diambil perPotongan, bukan sekaligus. Jumlah di bawah
        // sengaja melewati UKURAN_POTONGAN supaya ada record di potongan
        // kedua. Kalau pemotongan salah, record di potongan berikutnya
        // dilewati tanpa diproses dan perintah keluar dengan hasil yang terlihat
        // lengkap padahal fotonya masih di disk publik.
        $jumlah = RehomeEvidence::UKURAN_POTONGAN + 5;
        $user = User::factory()->petugas()->create();

        for ($i = 0; $i < $jumlah; $i++) {
            $path = "monitoring/batch-{$i}.jpg";

            WaterMonitoring::factory()->create([
                'user_id' => $user->id,
                'foto' => $path,
                'foto_disk' => 'public',
            ]);

            Storage::disk('public')->put($path, "isi-{$i}");
        }

        $this->artisan('evidence:rehome', ['--apply' => true])
            ->expectsOutputToContain("dipindahkan                     : {$jumlah}")
            ->assertSuccessful();

        $this->assertSame(0, WaterMonitoring::where('foto_disk', '!=', 'evidence')->count());

        // Record pertama dan terakhir sama-sama wajib ikut bergerak.
        Storage::disk('public')->assertMissing('monitoring/batch-0.jpg');
        Storage::disk('evidence')->assertExists('monitoring/batch-0.jpg');
        Storage::disk('public')->assertMissing('monitoring/batch-'.($jumlah - 1).'.jpg');
        Storage::disk('evidence')->assertExists('monitoring/batch-'.($jumlah - 1).'.jpg');
    }

    public function test_dry_run_classifies_exactly_like_apply(): void
    {
        // Simulasi dan eksekusi harus menetapkan klasifikasi yang sama.
        // Kalau ringkasan dry-run menyimpulkan "akan dipindahkan" padahal
        // eksekusi akan menyimpulkan bentrok, operator kehilangan arah.
        $isi = str_repeat('c', 1024);

        $this->makeLegacyMonitoring('monitoring/normal.jpg', $isi);
        $this->makeLegacyMonitoring('monitoring/setengah.jpg', $isi);
        Storage::disk('evidence')->put('monitoring/setengah.jpg', $isi);
        $this->makeLegacyMonitoring('monitoring/terpotong.jpg', $isi);
        Storage::disk('evidence')->put('monitoring/terpotong.jpg', 'pendek');
        $this->makeLegacyMonitoring('monitoring/hilang.jpg', null);
        $this->makeLegacyMonitoring('monitoring/yatim.jpg', null);
        Storage::disk('evidence')->put('monitoring/yatim.jpg', 'yatim');
        $this->makeRehomedMonitoring('monitoring/sisa.jpg', $isi, $isi);
        $this->makeRehomedMonitoring('monitoring/sisa-privat-hilang.jpg', $isi, null);

        $this->artisan('evidence:rehome')
            ->expectsOutputToContain('dipindahkan                     : 1')
            ->expectsOutputToContain('dipulihkan dari sisa eksekusi   : 3')
            ->expectsOutputToContain('file hilang                     : 1')
            ->expectsOutputToContain('bentrok / perlu ditinjau        : 2')
            ->expectsOutputToContain('gagal / tidak valid             : 0')
            ->expectsOutputToContain('masih terekspos di publik       : 0')
            ->assertFailed();

        // Eksekusi sungguhan harus menghitung persis sama. Kalau ringkasan
        // dry-run menyimpulkan sesuatu yang berbeda, operator sudah salah
        // membaca hasil simulasi.
        $this->artisan('evidence:rehome', ['--apply' => true])
            ->expectsOutputToContain('dipindahkan                     : 1')
            ->expectsOutputToContain('dipulihkan dari sisa eksekusi   : 3')
            ->expectsOutputToContain('file hilang                     : 1')
            ->expectsOutputToContain('bentrok / perlu ditinjau        : 2')
            ->expectsOutputToContain('gagal / tidak valid             : 0')
            ->expectsOutputToContain('masih terekspos di publik       : 0')
            ->assertFailed();

        // Dan hasil akhirnya harus sesuai dengan klasifikasi itu.
        $this->assertSame('evidence', $this->diskOf('monitoring/normal.jpg'));
        $this->assertSame('evidence', $this->diskOf('monitoring/setengah.jpg'));
        $this->assertSame('evidence', $this->diskOf('monitoring/yatim.jpg'));
        $this->assertSame('evidence', $this->diskOf('monitoring/sisa.jpg'));

        // Yang tidak terverifikasi tidak boleh bergerak sama sekali.
        $this->assertSame('public', $this->diskOf('monitoring/terpotong.jpg'));
        Storage::disk('public')->assertExists('monitoring/terpotong.jpg');

        // Duplikat yang sudah dipastikan sama boleh hilang dari publik.
        Storage::disk('public')->assertMissing('monitoring/sisa.jpg');
    }

    protected function diskOf(string $path): string
    {
        return WaterMonitoring::where('foto', $path)->value('foto_disk');
    }

    /**
     * Simulasikan kondisi "migrasi belum dijalankan" tanpa mengubah skema.
     *
     * Versi test ini sebelumnya benar-benar menjatuhkan kolom memakai DDL.
     * Pada MySQL DDL itu mengakhiri transaksi secara implisit, sehingga kolom
     * yang hilang ikut hilang untuk SELURUH test yang dijalankan sesudahnya
     * pada proses yang sama — termasuk test yang sama sekali tidak ada
     * hubungannya dengan migrasi ini. Efeknya persis seperti test tersembunyi
     * yang membocorkan keadaan ke test lain.
     *
     * Partial mock di bawah memberi jawaban yang sama tanpa menyentuh skema
     * sama sekali. Hanya kolom foto_disk yang dijawab berbeda; kolom lain
     * diteruskan ke SchemaBuilder asli supaya framework tetap berfungsi
     * normal saat perintah dijalankan.
     */
    protected function pretendFotoDiskColumnMissing(): void
    {
        $schema = Schema::getFacadeRoot();

        $mock = Mockery::mock($schema)->makePartial();
        $mock->shouldReceive('hasColumn')->andReturnUsing(
            static fn ($table, $columns) => $columns === 'foto_disk'
                ? false
                : $schema->hasColumn($table, $columns)
        );

        Schema::swap($mock);
    }

    public function test_apply_stops_with_a_clear_message_when_migration_has_not_run(): void
    {
        $this->makeLegacyMonitoring('monitoring/belum.jpg', 'konten');
        $this->pretendFotoDiskColumnMissing();

        $this->artisan('evidence:rehome', ['--apply' => true])
            ->expectsOutputToContain('Kolom foto_disk belum ada di tabel water_monitorings')
            ->expectsOutputToContain('php artisan migrate')
            ->assertFailed();

        Storage::disk('public')->assertExists('monitoring/belum.jpg');
        Storage::disk('evidence')->assertMissing('monitoring/belum.jpg');
    }

    /*
     |--------------------------------------------------------------------------
     | Pembatalan moveOne() tidak boleh melempar exception kedua
     |--------------------------------------------------------------------------
     |
     | Helper ini dipanggil dari dalam blok catch moveOne(). Kalau ia
     | melempar, exception kedua akan menutupi penyebab database yang
     | sebenarnya dan sisa file di disk privat akan lolos dari laporan.
     |
     | Diuji langsung lewat reflection karena memaksa update database
     | benar-benar gagal hanya bisa lewat DDL, yang pada MySQL memicu
     | implicit commit dan merusak sisa test di proses ini.
     |
     */

    public function test_rollback_reports_no_leftover_when_delete_succeeds(): void
    {
        $evidence = $this->breakEvidenceDiskDelete('monitoring/ok.jpg', throws: false, stillExists: false);

        $this->assertFalse(
            $this->hapusSalinanPrivat('monitoring/ok.jpg', $evidence),
            'File yang benar-benar terhapus tidak boleh dilaporkan sebagai sisa.'
        );
    }

    public function test_rollback_never_throws_when_delete_fails(): void
    {
        $evidence = $this->breakEvidenceDiskDelete('monitoring/kunci.jpg', throws: true, stillExists: true);

        $this->assertTrue(
            $this->hapusSalinanPrivat('monitoring/kunci.jpg', $evidence),
            'Delete yang melempar harus dilaporkan sebagai sisa, bukan dilempar lagi.'
        );
    }

    public function test_rollback_reports_leftover_when_delete_returns_false_and_file_remains(): void
    {
        $evidence = $this->breakEvidenceDiskDelete('monitoring/nyata.jpg', throws: false, stillExists: true);

        $this->assertTrue(
            $this->hapusSalinanPrivat('monitoring/nyata.jpg', $evidence),
            'File yang masih ada harus dilaporkan sebagai sisa meski delete() hanya mengembalikan false.'
        );
    }

    public function test_rollback_reports_no_leftover_when_delete_returns_false_but_file_is_gone(): void
    {
        $evidence = $this->breakEvidenceDiskDelete('monitoring/hilang.jpg', throws: false, stillExists: false);

        $this->assertFalse(
            $this->hapusSalinanPrivat('monitoring/hilang.jpg', $evidence),
            'File yang sudah tidak ada tidak boleh dilaporkan sebagai sisa.'
        );
    }

    public function test_rollback_treats_unverifiable_state_as_leftover(): void
    {
        // Pemeriksaan'baca' ikut gagal, sehingga kondisi file tidak bisa
        // dipastikan. Hasilnya harus dianggap sisa: lebih baik operator
        // memeriksa file yang sudah bersih daripada file yang tertinggal.
        $evidence = Mockery::mock(Storage::disk('evidence'))->makePartial();
        $evidence->shouldReceive('delete')
            ->andThrow(UnableToDeleteFile::atLocation('monitoring/kabur.jpg', 'file terkunci'));
        $evidence->shouldReceive('exists')
            ->andThrow(UnableToCheckFileExistence::forLocation('monitoring/kabur.jpg'));

        $this->assertTrue($this->hapusSalinanPrivat('monitoring/kabur.jpg', $evidence));
    }

    /**
     * Panggil helper pembatalan yang dilindungi di kelas perintah.
     */
    protected function hapusSalinanPrivat(string $path, $disk): bool
    {
        $command = app(RehomeEvidence::class);

        $method = new ReflectionMethod($command, 'hapusSalinanPrivat');
        $method->setAccessible(true);

        return $method->invoke($command, $path, $disk);
    }

    /**
     * Tiruan disk evidence untuk menguji jalur kegagalan pembatalan.
     *
     * Storage::fake() memakai filesystem in-memory yang tidak pernah gagal,
     * jadi kegagalan hanya bisa ditiru lewat mock. Setiap kegagalan
     * disimulasikan pada Disk yang dikembalikan, bukan pada facade, supaya
     * file sungguhan di disk publik tidak ikut berubah.
     */
    protected function breakEvidenceDiskDelete(string $path, bool $throws, bool $stillExists): Filesystem
    {
        $evidence = Storage::disk('evidence');
        $mock = Mockery::mock($evidence)->makePartial();

        if ($throws) {
            $mock->shouldReceive('delete')
                ->andThrow(UnableToDeleteFile::atLocation($path, 'file terkunci'));
        } else {
            $mock->shouldReceive('delete')->andReturnFalse();
        }

        $mock->shouldReceive('exists')->andReturn($stillExists);

        return $mock;
    }

    /**
     * Ganti disk publik dengan tiruan yang delete()-nya selalu gagal.
     *
     * Cara ini dipakai agar cabang kegagalan benar-benar diuji tanpa
     * bergantung pada perilaku sistem berkas. Melempar exception meniru disk
     * yang dikonfigurasi 'throw' => true.
     *
     * @return array{0: \Illuminate\Contracts\Filesystem\Filesystem, 1: \Illuminate\Contracts\Filesystem\Filesystem}
     */
    protected function breakPublicDiskDelete(bool $throwInstead, string $path = 'monitoring/kunci.jpg'): array
    {
        $public = Storage::disk('public');
        $evidence = Storage::disk('evidence');

        $mock = Mockery::mock($public)->makePartial();

        if ($throwInstead) {
            $mock->shouldReceive('delete')
                ->andThrow(UnableToDeleteFile::atLocation($path, 'file terkunci'));
        } else {
            $mock->shouldReceive('delete')->andReturnFalse();
        }

        Storage::shouldReceive('disk')->andReturnUsing(
            static fn (string $name) => $name === PhotoStorage::LEGACY_PUBLIC ? $mock : $evidence
        );

        return [$public, $evidence];
    }

    /**
     * Tiruan disk publik yang get()-nya mengembalikan null untuk satu path.
     *
     * Meniru disk 'public' yang dikonfigurasi 'throw' => false: file masih
     * ada sehingga exists() benar, tapi isinya tidak dapat dibaca sehingga
     * get() mengembalikan null alih-alih melempar exception. Inilah kondisi
     * yang membuat moveOne() dulu menulis file kosong lalu menghapus file
     * publik yang utuh.
     */
    protected function breakPublicDiskRead(string $path, bool $returnsNull = true): array
    {
        $public = Storage::disk('public');
        $evidence = Storage::disk('evidence');

        $mock = Mockery::mock($public)->makePartial();
        $mock->shouldReceive('get')->andReturnUsing(
            static fn (string $requested) => $requested === $path && $returnsNull
                ? null
                : $public->get($requested)
        );

        Storage::shouldReceive('disk')->andReturnUsing(
            static fn (string $name) => $name === PhotoStorage::LEGACY_PUBLIC ? $mock : $evidence
        );

        return [$public, $evidence];
    }

    /**
     * Tiruan disk privat yang put()-nya menulis isi terpotong.
     *
     * Penulisan yang terputus di tengah tidak melempar exception — file
     * sekadar berisi sebagian isi. Karena itu put() yang berhasil tidak
     * berarti data aman, dan hanya isVerifiedCopy() yang bisa menangkapnya.
     *
     * Sisa method diteruskan ke disk asli supaya file sungguhan di disk
     * publik tidak ikut berubah dan pemeriksaan size/get tetap realistis.
     */
    protected function truncateEvidencePut(string $path, int $keepBytes): Filesystem
    {
        $public = Storage::disk('public');
        $asli = Storage::disk('evidence');

        $mock = Mockery::mock($asli)->makePartial();
        $mock->shouldReceive('put')->andReturnUsing(
            static fn (string $requested, $contents) => $requested === $path
                ? $asli->put($requested, substr((string) $contents, 0, $keepBytes))
                : $asli->put($requested, $contents)
        );

        Storage::shouldReceive('disk')->andReturnUsing(
            static fn (string $name) => $name === PhotoStorage::EVIDENCE ? $mock : $public
        );

        return $mock;
    }

    /**
     * Tiruan disk privat yang isinya terpotong DAN tidak bisa dihapus.
     *
     * Berbeda dengan truncateEvidencePut(), di sini exists() ikut dikontrol.
     * Alasannya: perintah memeriksa keberadaan file di disk privat SEBELUM
     * memanggil moveOne(). Kalau exists() selalu true sejak awal, perintah
     * menganggap salinan sudah ada dan masuk ke cabang konflik — jalur yang
     * sedang diuji tidak akan pernah dieksekusi.
     *
     * Karena itu exists() baru mengembalikan true setelah put() sempat
     * menulis file terpotongnya, meniru urutan yang sebenarnya: file privat
     * belum ada saat pemeriksaan, lalu muncul terpotong setelah penyimpanan.
     */
    protected function breakEvidenceCopy(string $path, int $keepBytes, bool $undeletable = false): Filesystem
    {
        $public = Storage::disk('public');
        $asli = Storage::disk('evidence');
        $sudahDitulis = false;

        $mock = Mockery::mock($asli)->makePartial();
        $mock->shouldReceive('put')->andReturnUsing(
            static function (string $requested, $contents) use ($asli, $path, $keepBytes, &$sudahDitulis) {
                $sudahDitulis = true;

                return $requested === $path
                    ? $asli->put($requested, substr((string) $contents, 0, $keepBytes))
                    : $asli->put($requested, $contents);
            }
        );
        $mock->shouldReceive('exists')->andReturnUsing(
            static fn (string $requested) => $sudahDitulis || $asli->exists($requested)
        );

        if ($undeletable) {
            $mock->shouldReceive('delete')
                ->andThrow(UnableToDeleteFile::atLocation($path, 'file terkunci'));
        }

        Storage::shouldReceive('disk')->andReturnUsing(
            static fn (string $name) => $name === PhotoStorage::EVIDENCE ? $mock : $public
        );

        return $mock;
    }

    protected function makeLegacyMonitoring(string $path, ?string $content): WaterMonitoring
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => User::factory()->petugas()->create()->id,
            'foto' => $path,
            'foto_disk' => 'public',
        ]);

        if ($content !== null) {
            Storage::disk('public')->put($path, $content);
        }

        return $monitoring;
    }

    /**
     * Record yang kolomnya sudah menunjuk ke disk privat.
     *
     * Dipakai untuk membangun keadaan sisa: kolom sudah diperbarui tapi
     * file lama di disk publik belum tentu ikut terhapus. Isi pada kedua
     * disk sengaja dibiarkan opsional supaya semua kombinasi bisa diuji.
     */
    protected function makeRehomedMonitoring(string $path, ?string $isiPublik, ?string $isiPrivat): WaterMonitoring
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => User::factory()->petugas()->create()->id,
            'foto' => $path,
            'foto_disk' => 'evidence',
        ]);

        if ($isiPublik !== null) {
            Storage::disk('public')->put($path, $isiPublik);
        }

        if ($isiPrivat !== null) {
            Storage::disk('evidence')->put($path, $isiPrivat);
        }

        return $monitoring;
    }
}
