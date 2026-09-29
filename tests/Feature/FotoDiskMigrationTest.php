<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WaterMonitoring;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Test untuk migrasi yang menambahkan kolom foto_disk.
 *
 * Tidak ada satu pun test di sini yang menjalankan DDL. Kolom aslinya
 * sengaja dikunci lewat mock Schema dan hanya dicatat, bukan dieksekusi.
 * Alasannya bukan sekadarotiable: DDL pada MySQL mengakhiri transaksi
 * secara implisit, sehingga Whatever yang diubah akan bocor ke seluruh test
 * yang dijalankan sesudahnya pada proses yang sama. Test migrasi yang
 * menjalankan dropColumn() Karena itu bisa membuat test lain gagal dengan
 * pesan "Unknown column" yang sama sekali tidak berhubungan.
 */
class FotoDiskMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Perubahan skema yang diminta migrasi, format "tabel.kolom".
     *
     * @var array<int, string>
     */
    protected array $kolomDitambah = [];

    /**
     * Perubahan skema yang diminta migrasi, format "tabel.kolom".
     *
     * @var array<int, string>
     */
    protected array $kolomDijatuhkan = [];

    /**
     * Balasan untuk Schema::hasColumn(). Key "tabel.kolom".
     *
     * Nilai yang tidak ada di sini dijawab oleh skema sungguhan.
     *
     * @var array<string, bool>
     */
    protected array $kolomEksis = [];

    protected ?object $schemaAsli = null;

    protected ?object $migration = null;

    /**
     * Tiruan Schema dipasang untuk SETIAP test di kelas ini, bukan hanya
     * sebagian.
     *
     * Ini yang membuat kelas ini aman: tidak ada test di sini yang bisa
     * menjalankan DDL, termasuk yang tidak sengaja(assert schéma sungguhan).
     * DDL mengakhiri transaksi secara implisit, jadi satu test yang
     * menjatuhkan kolom sungguhan akan merusak seluruh test yang berjalan
     * sesudahnya — persis kegagalan yang seharusnya dicegah di sini.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->pretendSchemaIsFake();
    }

    protected function tearDown(): void
    {
        putenv('FORCE_DROP_FOTO_DISK');
        unset($_ENV['FORCE_DROP_FOTO_DISK'], $_SERVER['FORCE_DROP_FOTO_DISK']);

        parent::tearDown();
    }

    /*
     |--------------------------------------------------------------------------
     | up()
     |--------------------------------------------------------------------------
     */

    public function test_up_adds_the_column_to_both_tables_with_public_default(): void
    {
        $this->pretendBothTablesLackTheColumn();

        $this->migration()->up();

        $this->assertSame(
            ['water_monitorings.foto_disk', 'finance_reports.foto_disk'],
            $this->kolomDitambah
        );
    }

    public function test_up_skips_tables_that_already_have_the_column(): void
    {
        // Semua migrasi sudah berjalan, tapi up() dipanggil lagi.
        $this->migration()->up();

        $this->assertSame([], $this->kolomDitambah);
    }

    public function test_up_completes_the_table_that_a_previous_failure_missed(): void
    {
        // up() sebelumnya berhenti setelah tabel pertama berhasil.
        $this->pretendMissingOn('finance_reports');

        $this->migration()->up();

        $this->assertSame(
            ['finance_reports.foto_disk'],
            $this->kolomDitambah,
            'Hanya tabel yang belum punya kolom yang boleh disentuh.'
        );
    }

    /*
     |--------------------------------------------------------------------------
     | down() — idempotensi dan pemulihan up() yang gagal di tengah
     |--------------------------------------------------------------------------
     */

    public function test_down_drops_the_column_from_both_tables(): void
    {
        $this->migration()->down();

        $this->assertSame(
            ['water_monitorings.foto_disk', 'finance_reports.foto_disk'],
            $this->kolomDijatuhkan
        );
    }

    public function test_down_is_safe_to_run_twice(): void
    {
        $migration = $this->migration();

        $migration->down();
        $migration->down();

        $this->assertSame(
            ['water_monitorings.foto_disk', 'finance_reports.foto_disk'],
            $this->kolomDijatuhkan,
            'Kolom hanya boleh dijatuhkan satu kali per tabel meski down() dijalankan berulang.'
        );
    }

    public function test_down_only_touches_the_table_that_actually_has_the_column(): void
    {
        $this->pretendMissingOn('finance_reports');

        $this->migration()->down();

        $this->assertSame(
            ['water_monitorings.foto_disk'],
            $this->kolomDijatuhkan
        );
    }

    public function test_down_does_nothing_when_both_tables_already_lost_the_column(): void
    {
        $this->pretendBothTablesLackTheColumn();

        $this->migration()->down();

        $this->assertSame([], $this->kolomDijatuhkan);
    }

    /*
     |--------------------------------------------------------------------------
     | down() — penjaga data
     |--------------------------------------------------------------------------
     */

    public function test_down_refuses_to_drop_the_column_while_a_photo_lives_on_the_private_disk(): void
    {
        $this->makeMonitoringOnDisk('evidence');
        $this->makeMonitoringOnDisk('evidence');

        $this->expectException(RuntimeException::class);

        try {
            $this->migration()->down();
        } finally {
            $this->assertSame([], $this->kolomDijatuhkan, 'Tidak ada kolom yang boleh terjatuh.');
        }
    }

    public function test_refusal_message_names_the_table_the_count_and_the_way_out(): void
    {
        $this->makeMonitoringOnDisk('evidence');

        try {
            $this->migration()->down();
            $this->fail('down() seharusnya menolak menjatuhkan kolom.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('water_monitorings', $exception->getMessage());
            $this->assertStringContainsString('1 baris', $exception->getMessage());
            $this->assertStringContainsString('FORCE_DROP_FOTO_DISK=1', $exception->getMessage());
            $this->assertStringContainsString('tidak dapat dipulihkan lagi', $exception->getMessage());
        }
    }

    public function test_down_proceeds_when_the_operator_forces_it(): void
    {
        $this->makeMonitoringOnDisk('evidence');
        $this->forceDrop();

        $this->migration()->down();

        $this->assertSame(
            ['water_monitorings.foto_disk', 'finance_reports.foto_disk'],
            $this->kolomDijatuhkan
        );
    }

    public function test_down_is_not_blocked_when_every_photo_is_still_on_the_public_disk(): void
    {
        // Kasus paling umum: migrasi baru dijalankan, evidence:rehome belum
        // pernah dijalankan. Tidak ada yang perlu dilindungi.
        $this->makeMonitoringOnDisk('public');

        $this->migration()->down();

        $this->assertSame(
            ['water_monitorings.foto_disk', 'finance_reports.foto_disk'],
            $this->kolomDijatuhkan
        );
    }

    public function test_refusal_only_checks_tables_that_have_the_column(): void
    {
        // finance_reports sengaja dianggap belum punya kolom, meniru up()
        // yang gagal di tengah. Bila penjaga tetap scheiding ke tabel itu,
        // query-nya akan melukai "Unknown column" dan pesan yang asli hilang.
        $this->pretendMissingOn('finance_reports');
        $this->makeMonitoringOnDisk('evidence');

        try {
            $this->migration()->down();
            $this->fail('down() seharusnya menolak menjatuhkan kolom.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('water_monitorings', $exception->getMessage());
            $this->assertStringNotContainsString('finance_reports', $exception->getMessage());
        }
    }

    /*
     |--------------------------------------------------------------------------
     | Bantuan
     |--------------------------------------------------------------------------
     */

    /**
     * Muat instance migrasi yang sama seperti yang dijalankan Artisan.
     *
     * Di-cache supaya file tidak di-require berulang kali: setiap require
     * membuat kelas anonim baru, dan itu tidak perlu di sini.
     */
    protected function migration(): object
    {
        return $this->migration ??= require database_path(
            'migrations/2026_09_28_150000_add_foto_disk_to_photo_tables.php'
        );
    }

    /**
     * Ganti Schema dengan tiruan yang mencatat perubahan tanpa mengeksekusinya.
     *
     * Blueprint juga harus tiruan: kalau tidak, closure yang dioper oleh
     * migrasi tidak pernah dipanggil dan tidak ada yang tercatat.
     *
     * SchemaBuilder asli ditangkap sekali lalu dipakai ulang. Mengambilnya
     * ulang setelah facade di-swap akan membuat tiruan dari tiruan, dan
     * Mockery gagal karena nama kelas hasil turunannya bentrok.
     */
    protected function pretendSchemaIsFake(): void
    {
        if ($this->schemaAsli !== null) {
            return;
        }

        $schema = $this->schemaAsli = Schema::getFacadeRoot();
        $test = $this;

        $mock = Mockery::mock($schema)->makePartial();

        $mock->shouldReceive('hasColumn')->andReturnUsing(
            fn ($table, $column) => $test->kolomEksis["{$table}.{$column}"]
                ?? $schema->hasColumn($table, $column)
        );

        $mock->shouldReceive('table')->andReturnUsing(
            function (string $table, ?callable $callback = null) use ($test) {
                $blueprint = Mockery::mock(Blueprint::class)->makePartial();

                // Status kolom ikut diperbarui supaya tiruan ini berperilaku
                // seperti skema sungguhan. Tanpa itu, hasColumn() akan selalu
                // menjawab "ada" dan down() kedua terlihat mengulang
                // penghapusan yang sama.
                $blueprint->shouldReceive('string')->andReturnUsing(
                    function (string $column, ?string $type = null) use ($test, $table) {
                        $test->kolomDitambah[] = "{$table}.{$column}";
                        $test->kolomEksis["{$table}.{$column}"] = true;

                        // ColumnDefinition sungguhan dipakai supaya rantai
                        // ->default()->after() di migrasi tetap bekerja
                        // seperti aslinya. Konstruktornya mewarisi Fluent
                        // dan hanya menerima satu array atribut.
                        return new ColumnDefinition([
                            'name' => $column,
                            'type' => $type ?? 'string',
                        ]);
                    }
                );

                $blueprint->shouldReceive('dropColumn')->andReturnUsing(
                    function ($columns) use ($test, $table): void {
                        foreach ((array) $columns as $column) {
                            $test->kolomDijatuhkan[] = "{$table}.{$column}";
                            $test->kolomEksis["{$table}.{$column}"] = false;
                        }
                    }
                );

                if ($callback !== null) {
                    $callback($blueprint);
                }

                return $blueprint;
            }
        );

        Schema::swap($mock);
    }

    protected function pretendBothTablesLackTheColumn(): void
    {
        $this->pretendMissingOn('water_monitorings');
        $this->pretendMissingOn('finance_reports');
    }

    protected function pretendMissingOn(string $table): void
    {
        $this->kolomEksis["{$table}.foto_disk"] = false;
    }

    protected function makeMonitoringOnDisk(string $disk): WaterMonitoring
    {
        return WaterMonitoring::factory()->create([
            'user_id' => User::factory()->petugas()->create()->id,
            'foto' => 'monitoring/'.strtolower($disk).'.jpg',
            'foto_disk' => $disk,
        ]);
    }

    /**
     * Setel environment variable dengan semua cara yang mungkin dibaca
     * framework, lalu bersihkan lagi di tearDown().
     */
    protected function forceDrop(): void
    {
        putenv('FORCE_DROP_FOTO_DISK=1');
        $_ENV['FORCE_DROP_FOTO_DISK'] = '1';
        $_SERVER['FORCE_DROP_FOTO_DISK'] = '1';
    }
}
