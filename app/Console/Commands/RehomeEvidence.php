<?php

namespace App\Console\Commands;

use App\Support\PhotoStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Memindahkan foto bukti lama dari disk publik ke disk privat.
 *
 * Tanpa --apply perintah ini hanya melaporkan rencana dan tidak mengubah
 * file maupun database. Foto yang sudah berada di disk 'evidence'
 * dilewati.
 */
class RehomeEvidence extends Command
{
    protected $signature = 'evidence:rehome
                            {--apply : Jalankan perpindahan file dan update database. Tanpa flag ini hanya simulasi.}';

    protected $description = 'Pindahkan foto bukti dari disk publik ke disk privat (default simulasi).';

    /**
     * Status yang dikembalikan moveOne().
     */
    public const STATUS_DIPINDAH = 'dipindah';

    public const STATUS_TERSISA = 'tersisa';

    public const STATUS_GAGAL = 'gagal';

    /**
     * Tabel yang menyimpan foto bukti.
     *
     * @var array<int, string>
     */
    protected array $tables = ['water_monitorings', 'finance_reports'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $this->line($apply
            ? '<fg=yellow>MODE: APLIKASI — file akan dipindah dan database diperbarui.</>'
            : '<fg=cyan>MODE: SIMULASI (dry-run) — tidak ada yang diubah. Tambahkan --apply untuk eksekusi.</>');
        $this->newLine();

        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'foto_disk')) {
                $this->error("Kolom foto_disk belum ada di tabel {$table}.");
                $this->line('Jalankan <fg=cyan>php artisan migrate</> terlebih dahulu, lalu ulangi perintah ini.');

                return self::FAILURE;
            }
        }

        $public = Storage::disk(PhotoStorage::LEGACY_PUBLIC);
        $evidence = Storage::disk(PhotoStorage::EVIDENCE);

        $total = ['pindah' => 0, 'sudah' => 0, 'hilang' => 0, 'konflik' => 0, 'gagal' => 0, 'tersisa' => 0];

        foreach ($this->tables as $table) {
            $this->line("<fg=white>[$table]</>");

            $sudah = DB::table($table)
                ->where('foto_disk', PhotoStorage::EVIDENCE)
                ->count();
            $total['sudah'] += $sudah;

            if ($sudah > 0) {
                $this->line("  <fg=green>{$sudah} record sudah berada di disk privat.</>");
            }

            $rows = DB::table($table)
                ->where('foto_disk', '!=', PhotoStorage::EVIDENCE)
                ->orderBy('id')
                ->get(['id', 'foto', 'foto_disk']);

            foreach ($rows as $row) {
                $path = PhotoStorage::safePath($row->foto);

                if ($path === null) {
                    $total['gagal']++;
                    $this->line("  <fg=red>id {$row->id}: path foto tidak valid, dilewati.</>");

                    continue;
                }

                if (! $public->exists($path)) {
                    $total['hilang']++;
                    $this->line("  <fg=yellow>id {$row->id}: file '{$path}' tidak ada di disk publik, dilewati.</>");

                    continue;
                }

                if ($evidence->exists($path)) {
                    $total['konflik']++;
                    $this->line("  <fg=yellow>id {$row->id}: '{$path}' sudah ada di disk privat, dilewati.</>");

                    continue;
                }

                if (! $apply) {
                    $total['pindah']++;
                    $this->line("  id {$row->id}: akan dipindah '{$path}'");

                    continue;
                }

                $status = $this->moveOne($table, $row->id, $path, $public, $evidence);

                if ($status === self::STATUS_GAGAL) {
                    $total['gagal']++;
                } else {
                    $total['pindah']++;

                    if ($status === self::STATUS_TERSISA) {
                        $total['tersisa']++;
                    }
                }
            }

            $this->newLine();
        }

        $this->line('<fg=white>Ringkasan:</>');
        $this->line("  dipindahkan / akan dipindah : {$total['pindah']}");
        $this->line("  sudah di disk privat         : {$total['sudah']}");
        $this->line("  file hilang                  : {$total['hilang']}");
        $this->line("  bentrok                     : {$total['konflik']}");
        $this->line("  gagal / tidak valid          : {$total['gagal']}");
        $this->line("  masih terekspos di publik    : {$total['tersisa']}");

        if (! $apply && $total['pindah'] > 0) {
            $this->newLine();
            $this->line('<fg=cyan>Simulasi selesai. Jalankan: php artisan evidence:rehome --apply</>');
        }

        // File hilang, bentrok, gagal diproses, atau file lama yang masih
        // tertinggal di disk publik semuanya berarti ada yang perlu
        // ditindaklanjuti operator, jadi kode keluar ditandai gagal meski
        // perintah tidak fatal.
        $perluTindakLanjut = $total['gagal'] + $total['hilang']
            + $total['konflik'] + $total['tersisa'];

        return $perluTindakLanjut > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Pindahkan satu file lalu perbarui kolom foto_disk.
     *
     * Urutan: salin ke disk privat -> update database -> hapus dari disk
     * publik. Bila update database gagal, salinan di disk privat dihapus
     * kembali sehingga file lama tetap utuh di disk publik.
     *
     * Mengembalikan salah satu STATUS_* di atas. STATUS_TERSISA berarti
     * database sudah diperbarui tetapi file lama belum terhapus dari disk
     * publik, sehingga foto itu masih bisa diakses lewat /storage dan wajib
     * dilaporkan ke operator.
     */
    protected function moveOne(string $table, int $id, string $path, $public, $evidence): string
    {
        try {
            $evidence->put($path, $public->get($path));
        } catch (Throwable $exception) {
            $this->line("  <fg=red>id {$id}: gagal menyalin ke disk privat ({$exception->getMessage()})</>");

            return self::STATUS_GAGAL;
        }

        try {
            DB::table($table)->where('id', $id)->update(['foto_disk' => PhotoStorage::EVIDENCE]);
        } catch (Throwable $exception) {
            $evidence->delete($path);

            $this->line("  <fg=red>id {$id}: update database gagal, file dikembalikan ({$exception->getMessage()})</>");

            return self::STATUS_GAGAL;
        }

        // Disk yang dikonfigurasi dengan 'throw' => true akan melempar
        // exception alih-alih mengembalikan false, jadi keduanya harus
        // ditangani agar file yang masih terekspos tidak lolos dari laporan.
        try {
            $terhapus = $public->delete($path);
        } catch (Throwable $exception) {
            $terhapus = false;
        }

        if (! $terhapus) {
            $this->line("  <fg=yellow>id {$id}: database diperbarui tetapi file lama masih terekspos di disk publik.</>");

            return self::STATUS_TERSISA;
        }

        $this->line("  <fg=green>id {$id}: dipindah '{$path}'</>");

        return self::STATUS_DIPINDAH;
    }
}
