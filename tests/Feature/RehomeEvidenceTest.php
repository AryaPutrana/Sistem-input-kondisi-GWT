<?php

namespace Tests\Feature;

use App\Models\FinanceReport;
use App\Models\User;
use App\Models\WaterMonitoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_apply_leaves_public_copy_when_target_already_exists(): void
    {
        $monitoring = $this->makeLegacyMonitoring('monitoring/bentrok.jpg', 'versi-lama');
        Storage::disk('evidence')->put('monitoring/bentrok.jpg', 'versi-baru');

        $this->artisan('evidence:rehome', ['--apply' => true])->assertFailed();

        $this->assertSame('public', $monitoring->fresh()->foto_disk);
        Storage::disk('public')->assertExists('monitoring/bentrok.jpg');
        $this->assertSame('versi-baru', Storage::disk('evidence')->get('monitoring/bentrok.jpg'));
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
}
