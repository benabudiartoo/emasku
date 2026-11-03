<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\ZakatPeriod> */
class ZakatPeriodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'valuation_date' => '2026-11-03',
            'madhhab' => 'syafii',
            'rule_version' => 'test-1',
            'haul_start' => '2025-10-01',
            'haul_end' => '2026-09-20',
            'haul_start_hijri' => '1447-04-09',
            'haul_end_hijri' => '1448-04-09',
            'contributions' => [],
            'reasons' => [],
            'steps' => [],
            'nisab_g' => '85.0000000000',
            'rate' => '0.025000',
            'eligible_g' => '100.0000000000',
            'price_per_gram' => null,
            'zakat_g' => '2.5000000000',
            'zakat_rupiah' => null,
            'status' => 'complete',
        ];
    }
}
