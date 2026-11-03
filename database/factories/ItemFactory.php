<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Item> */
class ItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => 'bar',
            'name' => 'Batangan Antam 10 g',
            'brand' => 'Antam',
            'unit_weight_g' => '10.0000',
            'purity_fraction' => '0.999900',
            'purity_source' => 'permil',
            'purity_input' => '999.9000',
            'ownership' => 'own',
            'acquisition_method' => 'purchase',
            'owned_since' => '2024-01-15',
            'owned_since_certainty' => 'exact',
        ];
    }

    public function jewelry(): static
    {
        return $this->state(fn () => [
            'kind' => 'jewelry',
            'name' => 'Cincin Kawin',
            'brand' => null,
            'jewelry_type' => 'cincin',
            'unit_weight_g' => null,
            'gross_weight_g' => '8.0000',
            'stone_state' => 'known',
            'stone_weight_g' => '2.0000',
            'purity_fraction' => '0.750000',
            'purity_source' => 'permil',
            'purity_input' => '750.0000',
            'purpose' => 'undecided',
        ]);
    }
}
