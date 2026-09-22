<?php

namespace Tests\Feature;

use App\Models\FinanceLocation;
use App\Models\FinanceReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinanceReportCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_petugas_can_store_finance_report(): void
    {
        Storage::fake('public');

        $petugas = User::factory()->petugas()->create();
        $location = FinanceLocation::factory()->create();

        $response = $this->actingAs($petugas)->post(route('keuangan.store'), [
            'tanggal' => '2026-09-10',
            'location_id' => $location->id,
            'kondisi' => 'normal',
            'keterangan' => 'Kondisi normal bulan September.',
            'foto' => UploadedFile::fake()->image('foto.jpg'),
        ]);

        $response->assertRedirect(route('keuangan.riwayat', ['bulan' => '2026-09']));
        $response->assertSessionHas('success');

        $record = FinanceReport::first();
        $this->assertNotNull($record);
        $this->assertDatabaseHas('finance_reports', [
            'id' => $record->id,
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'kondisi' => 'normal',
        ]);
        Storage::disk('public')->assertExists($record->foto);
    }

    public function test_foto_is_required(): void
    {
        $petugas = User::factory()->petugas()->create();
        $location = FinanceLocation::factory()->create();

        $response = $this->actingAs($petugas)->post(route('keuangan.store'), [
            'tanggal' => '2026-09-10',
            'location_id' => $location->id,
            'kondisi' => 'normal',
            'keterangan' => 'Tanpa foto.',
        ]);

        $response->assertSessionHasErrors('foto');
        $this->assertDatabaseCount('finance_reports', 0);
    }

    public function test_petugas_riwayat_only_shows_own_reports(): void
    {
        $petugas = User::factory()->petugas()->create();
        $other = User::factory()->petugas()->create();
        $location = FinanceLocation::factory()->create();

        FinanceReport::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'tanggal' => '2026-09-01',
        ]);
        FinanceReport::factory()->create([
            'user_id' => $other->id,
            'location_id' => $location->id,
            'tanggal' => '2026-09-02',
        ]);

        $response = $this->actingAs($petugas)->get(route('keuangan.riwayat', ['bulan' => '2026-09']));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('reports')->total());
    }

    public function test_admin_can_view_index(): void
    {
        $admin = User::factory()->admin()->create();
        $petugas = User::factory()->petugas()->create();
        $location = FinanceLocation::factory()->create();

        FinanceReport::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'tanggal' => '2026-09-01',
        ]);

        $response = $this->actingAs($admin)->get(route('keuangan.index', ['bulan' => '2026-09']));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('reports')->total());
    }

    public function test_petugas_cannot_modify_other_report(): void
    {
        $owner = User::factory()->petugas()->create();
        $other = User::factory()->petugas()->create();
        $location = FinanceLocation::factory()->create();
        $report = FinanceReport::factory()->create([
            'user_id' => $owner->id,
            'location_id' => $location->id,
            'tanggal' => '2026-09-01',
        ]);

        $response = $this->actingAs($other)->put(route('keuangan.update', $report), [
            'tanggal' => '2026-09-01',
            'location_id' => $location->id,
            'kondisi' => 'normal',
            'keterangan' => 'Diubah orang lain.',
        ]);
        $response->assertForbidden();

        $this->actingAs($other)->delete(route('keuangan.destroy', $report))->assertForbidden();

        $this->assertDatabaseHas('finance_reports', [
            'id' => $report->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_petugas_update_own_report_redirects_to_riwayat(): void
    {
        $petugas = User::factory()->petugas()->create();
        $location = FinanceLocation::factory()->create();
        $report = FinanceReport::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'tanggal' => '2026-09-01',
        ]);

        $response = $this->actingAs($petugas)->put(route('keuangan.update', $report), [
            'tanggal' => '2026-09-05',
            'location_id' => $location->id,
            'kondisi' => 'debit_air_kurang',
            'keterangan' => 'Diperbarui oleh petugas.',
        ]);

        $response->assertRedirect(route('keuangan.riwayat', ['bulan' => '2026-09']));

        $this->assertDatabaseHas('finance_reports', [
            'id' => $report->id,
            'kondisi' => 'debit_air_kurang',
            'keterangan' => 'Diperbarui oleh petugas.',
        ]);
    }

    public function test_admin_can_update_and_delete_any_report(): void
    {
        $admin = User::factory()->admin()->create();
        $petugas = User::factory()->petugas()->create();
        $location = FinanceLocation::factory()->create();
        $report = FinanceReport::factory()->create([
            'user_id' => $petugas->id,
            'location_id' => $location->id,
            'tanggal' => '2026-09-01',
        ]);

        $response = $this->actingAs($admin)->put(route('keuangan.update', $report), [
            'tanggal' => '2026-09-01',
            'location_id' => $location->id,
            'kondisi' => 'normal',
            'keterangan' => 'Diperbarui oleh admin.',
        ]);
        $response->assertRedirect(route('keuangan.index', ['bulan' => '2026-09']));

        $this->assertDatabaseHas('finance_reports', [
            'id' => $report->id,
            'keterangan' => 'Diperbarui oleh admin.',
        ]);

        $this->actingAs($admin)->delete(route('keuangan.destroy', $report))->assertRedirect();
        $this->assertDatabaseMissing('finance_reports', ['id' => $report->id]);
    }
}