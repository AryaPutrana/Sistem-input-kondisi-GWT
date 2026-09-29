<?php

namespace Tests\Feature;

use App\Http\Controllers\FinanceReportController;
use App\Models\FinanceReport;
use App\Models\User;
use App\Support\PhotoStorage;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToReadFile;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class FinanceReportPdfTest extends TestCase
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
    | 2.1f - fotoToDataUri harus membaca file sesuai kolom foto_disk
    |--------------------------------------------------------------------------
    */

    public function test_data_uri_is_built_from_the_evidence_disk(): void
    {
        Storage::disk('evidence')->put('keuangan/bukti.jpg', $this->jpegBytes());

        $report = FinanceReport::factory()->create([
            'foto' => 'keuangan/bukti.jpg',
            'foto_disk' => 'evidence',
        ]);

        $this->assertStringStartsWith(
            'data:image/jpeg;base64,',
            (string) $this->fotoToDataUri($report)
        );
    }

    public function test_data_uri_is_built_from_the_legacy_public_disk(): void
    {
        Storage::disk('public')->put('keuangan/lama.jpg', $this->jpegBytes());

        $report = FinanceReport::factory()->create([
            'foto' => 'keuangan/lama.jpg',
            'foto_disk' => 'public',
        ]);

        $this->assertStringStartsWith(
            'data:image/jpeg;base64,',
            (string) $this->fotoToDataUri($report)
        );
    }

    public function test_unknown_disk_value_falls_back_to_public(): void
    {
        Storage::disk('public')->put('keuangan/lama.jpg', $this->jpegBytes());

        $report = FinanceReport::factory()->create([
            'foto' => 'keuangan/lama.jpg',
            'foto_disk' => 'entah',
        ]);

        $this->assertStringStartsWith(
            'data:image/jpeg;base64,',
            (string) $this->fotoToDataUri($report)
        );
    }

    public function test_data_uri_is_null_when_file_is_missing_from_disk(): void
    {
        $report = FinanceReport::factory()->create([
            'foto' => 'keuangan/hilang.jpg',
            'foto_disk' => 'evidence',
        ]);

        $this->assertNull($this->fotoToDataUri($report));
    }

    public function test_data_uri_is_null_when_foto_column_is_empty(): void
    {
        $report = FinanceReport::factory()->create([
            'foto' => '',
            'foto_disk' => 'evidence',
        ]);

        $this->assertNull($this->fotoToDataUri($report));
    }

    public function test_data_uri_is_null_for_path_traversal(): void
    {
        Storage::disk('evidence')->put('keuangan/nyata.jpg', $this->jpegBytes());

        $report = FinanceReport::factory()->create([
            'foto' => '../../keuangan/nyata.jpg',
            'foto_disk' => 'evidence',
        ]);

        $this->assertNull($this->fotoToDataUri($report));
    }

    public function test_data_uri_is_null_when_file_exists_but_cannot_be_read(): void
    {
        // exists() bilang file ada, tapi get() gagal — misalnya karena
        // permission file berubah sementara permission direktorinya tidak.
        // Pasangan exists() lalu get() akan melempar di sini dan menggagalkan
        // seluruh unduhan PDF, bukan hanya foto yang bermasalah.
        $report = FinanceReport::factory()->create([
            'foto' => 'keuangan/tidak-terbaca.jpg',
            'foto_disk' => 'evidence',
        ]);

        $this->mockUnreadablePhoto('keuangan/tidak-terbaca.jpg');

        $this->assertNull($this->fotoToDataUri($report));
    }

    public function test_pdf_is_still_valid_when_one_photo_cannot_be_read(): void
    {
        Storage::disk('evidence')->put('keuangan/nyata.jpg', $this->jpegBytes());
        Storage::disk('evidence')->put('keuangan/rusak.jpg', $this->jpegBytes());

        FinanceReport::factory()->create([
            'tanggal' => '2026-09-10',
            'foto' => 'keuangan/nyata.jpg',
            'foto_disk' => 'evidence',
        ]);
        FinanceReport::factory()->create([
            'tanggal' => '2026-09-11',
            'foto' => 'keuangan/rusak.jpg',
            'foto_disk' => 'evidence',
        ]);

        $this->mockUnreadablePhoto('keuangan/rusak.jpg');

        $response = $this->actingAs($this->admin())->get(
            route('keuangan.pdf', ['bulan' => '2026-09'])
        );

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $this->pdfBody($response));
    }

    /*
    |--------------------------------------------------------------------------
    | 2.1f - endpoint unduh PDF
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_download_a_valid_pdf(): void
    {
        FinanceReport::factory()->create(['tanggal' => '2026-09-10']);

        $response = $this->actingAs($this->admin())->get(
            route('keuangan.pdf', ['bulan' => '2026-09'])
        );

        $response->assertOk();
        $this->assertStringContainsString(
            'application/pdf',
            (string) $response->headers->get('Content-Type')
        );
        $this->assertStringStartsWith('%PDF', $this->pdfBody($response));
    }

    public function test_guest_is_redirected_to_login_when_downloading_pdf(): void
    {
        $this->get(route('keuangan.pdf', ['bulan' => '2026-09']))
            ->assertRedirect(route('login'));
    }

    public function test_pdf_embeds_the_photo_from_the_evidence_disk(): void
    {
        FinanceReport::factory()->create([
            'tanggal' => '2026-09-10',
            'foto' => 'keuangan/bukti.jpg',
            'foto_disk' => 'evidence',
        ]);
        Storage::disk('evidence')->put('keuangan/bukti.jpg', $this->jpegBytes());

        $withPhoto = $this->pdfSize($this->admin(), '2026-09');

        $empty = FinanceReport::query()->where('foto', 'keuangan/bukti.jpg')->first();
        Storage::disk('evidence')->delete($empty->foto);

        $withoutPhoto = $this->pdfSize($this->admin(), '2026-09');

        $this->assertGreaterThan(
            2000,
            $withPhoto - $withoutPhoto,
            'Foto dari disk evidence tidak ikut ter-embed ke PDF.'
        );
    }

    public function test_pdf_is_still_valid_when_the_photo_file_is_missing(): void
    {
        FinanceReport::factory()->create([
            'tanggal' => '2026-09-10',
            'foto' => 'keuangan/hilang.jpg',
            'foto_disk' => 'evidence',
        ]);

        $response = $this->actingAs($this->admin())->get(
            route('keuangan.pdf', ['bulan' => '2026-09'])
        );

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $this->pdfBody($response));
    }

    public function test_pdf_filename_reflects_the_requested_month(): void
    {
        $this->actingAs($this->admin())
            ->get(route('keuangan.pdf', ['bulan' => '2026-09']))
            ->assertHeader('Content-Disposition', 'attachment; filename=laporan-kondisi-air-2026-09.pdf');
    }

    public function test_pdf_only_includes_records_from_the_requested_month(): void
    {
        foreach (range(1, 3) as $day) {
            FinanceReport::factory()->create([
                'tanggal' => "2026-09-0{$day}",
                'foto' => "keuangan/sep-{$day}.jpg",
                'foto_disk' => 'evidence',
            ]);
            Storage::disk('evidence')->put("keuangan/sep-{$day}.jpg", $this->jpegBytes());
        }

        $september = $this->pdfSize($this->admin(), '2026-09');
        $oktober = $this->pdfSize($this->admin(), '2026-10');

        $this->assertGreaterThan(
            5000,
            $september - $oktober,
            'PDF bulan terpilih seharusnya hanya memuat record bulan itu.'
        );
    }

    public function test_invalid_month_falls_back_to_the_current_month(): void
    {
        $this->actingAs($this->admin())
            ->get(route('keuangan.pdf', ['bulan' => 'bukan-bulan']))
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename=laporan-kondisi-air-'.now()->format('Y-m').'.pdf'
            );
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    protected function pdfSize(User $admin, string $bulan): int
    {
        return strlen($this->pdfBody(
            $this->actingAs($admin)->get(route('keuangan.pdf', ['bulan' => $bulan]))
        ));
    }

    protected function pdfBody($response): string
    {
        return (string) $response->getContent();
    }

    protected function fotoToDataUri(?FinanceReport $report): ?string
    {
        $controller = app(FinanceReportController::class);

        $method = new ReflectionMethod($controller, 'fotoToDataUri');
        $method->setAccessible(true);

        return $method->invoke($controller, $report);
    }

    /**
     * Buat file tertentu terlihat ada tapi tidak bisa dibaca.
     *
     * exists() dan get() sengaja dibedakan: fileExists() pada driver lokal
     * hanya memanggil is_file() sehingga tidak pernah melempar, sedangkan
     * read() melempar UnableToReadFile saat file_get_contents() gagal. Dua
     * kondisi itu yang harus bisa ditangani tanpa menggagalkan PDF.
     */
    protected function mockUnreadablePhoto(string $path): void
    {
        $manager = app('filesystem');
        $evidence = Storage::disk('evidence');
        $mock = Mockery::mock($evidence)->makePartial();

        $mock->shouldReceive('exists')->andReturnTrue();
        $mock->shouldReceive('get')->andReturnUsing(
            static function (string $requested) use ($evidence) {
                if (str_contains($requested, 'tidak-terbaca') || str_contains($requested, 'rusak')) {
                    throw UnableToReadFile::fromLocation($requested, 'permission denied');
                }

                return $evidence->get($requested);
            }
        );

        $factory = Mockery::mock(FilesystemFactoryContract::class);
        $factory->shouldReceive('disk')->andReturnUsing(
            static fn ($name = null) => $name === PhotoStorage::EVIDENCE ? $mock : $manager->disk($name)
        );

        Storage::swap($factory);
    }

    protected function jpegBytes(int $width = 400, int $height = 300): string
    {
        $image = imagecreatetruecolor($width, $height);

        mt_srand(20260928);

        for ($x = 0; $x < $width; $x += 4) {
            for ($y = 0; $y < $height; $y += 4) {
                imagesetpixel(
                    $image,
                    $x,
                    $y,
                    imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255))
                );
            }
        }

        ob_start();
        imagejpeg($image, null, 70);
        $bytes = (string) ob_get_clean();

        imagedestroy($image);

        return $bytes;
    }
}
