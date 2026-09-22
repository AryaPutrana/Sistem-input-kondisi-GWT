<?php

namespace Database\Factories;

use App\Models\MonitoringLocation;
use App\Models\User;
use App\Models\WaterMonitoring;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WaterMonitoring>
 */
class WaterMonitoringFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Catatan: unik per (location_id, tanggal, sesi). Beri nilai eksplisit
     * pada atribut tersebut di dalam test untuk menghindari tabrakan.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sesi = array_rand(WaterMonitoring::SESI);

        return [
            'user_id' => User::factory(),
            'location_id' => MonitoringLocation::factory(),
            'tanggal' => fake()->date('Y-m-d'),
            'sesi' => $sesi,
            'waktu' => WaterMonitoring::SESI_WAKTU[$sesi],
            'kondisi' => 'normal',
            'keterangan' => fake()->sentence(),
            'foto' => 'monitoring/test.jpg',
        ];
    }
}