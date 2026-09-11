<?php

namespace Database\Seeders;

use App\Models\MonitoringLocation;
use Illuminate\Database\Seeder;

class MonitoringLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = [
            ['nama_lokasi' => 'GWT', 'jenis' => 'gwt', 'keterangan' => 'Ground Water Tank utama', 'status' => 'aktif'],
            ['nama_lokasi' => 'Kolam Air Bersih', 'jenis' => 'kolam_air_bersih', 'keterangan' => 'Kolam air bersih utama', 'status' => 'aktif'],
        ];

        foreach ($locations as $location) {
            MonitoringLocation::firstOrCreate(
                ['nama_lokasi' => $location['nama_lokasi']],
                $location
            );
        }
    }
}
