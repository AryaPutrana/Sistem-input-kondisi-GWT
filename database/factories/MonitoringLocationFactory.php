<?php

namespace Database\Factories;

use App\Models\MonitoringLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MonitoringLocation>
 */
class MonitoringLocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama_lokasi' => fake()->unique()->streetName().' GWT',
            'keterangan' => fake()->sentence(),
            'status' => 'aktif',
        ];
    }

    /**
     * Lokasi nonaktif.
     *
     * @return $this
     */
    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'nonaktif',
        ]);
    }
}