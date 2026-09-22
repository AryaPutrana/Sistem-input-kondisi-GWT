<?php

namespace Database\Factories;

use App\Models\FinanceLocation;
use App\Models\FinanceReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FinanceReport>
 */
class FinanceReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'location_id' => FinanceLocation::factory(),
            'tanggal' => fake()->date('Y-m-d'),
            'kondisi' => 'normal',
            'keterangan' => fake()->sentence(),
            'foto' => 'keuangan/test.jpg',
        ];
    }
}