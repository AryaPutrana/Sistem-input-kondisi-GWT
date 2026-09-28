<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Menambahkan kolom foto_disk pada kedua tabel foto bukti.
     *
     * Default-nya 'public' dengan sengaja: seluruh baris yang sudah ada
     * tetap menunjuk disk publik sehingga aplikasi tidak langsung rusak
     * setelah migrasi ini. Foto baru disimpan ke disk privat 'evidence'.
     * Pemindahan file lama ke disk privat dilakukan terpisah melalui
     * perintah `php artisan evidence:rehome`.
     */
    public function up(): void
    {
        Schema::table('water_monitorings', function (Blueprint $table) {
            $table->string('foto_disk')->default('public')->after('foto');
        });

        Schema::table('finance_reports', function (Blueprint $table) {
            $table->string('foto_disk')->default('public')->after('foto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('water_monitorings', function (Blueprint $table) {
            $table->dropColumn('foto_disk');
        });

        Schema::table('finance_reports', function (Blueprint $table) {
            $table->dropColumn('foto_disk');
        });
    }
};
