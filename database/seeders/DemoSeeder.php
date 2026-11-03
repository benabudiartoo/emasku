<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Fictional demo data only. Refuses to run in production. Safe to run twice: it leaves an existing demo user alone.
 *
 *   php artisan db:seed --class=DemoSeeder      (sign in as demo@contoh.test / demo-12345)
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder tidak dijalankan di production.');

            return;
        }

        $user = User::firstOrCreate(
            ['email' => 'demo@contoh.test'],
            ['name' => 'Pengguna Demo', 'password' => 'demo-12345'],
        );

        if (Item::where('user_id', $user->id)->exists()) {
            $this->command?->info('Data demo sudah ada, dilewati.');

            return;
        }

        $home = $this->location($user, 'Brankas rumah', 'safe');
        $bank = $this->location($user, 'Safe deposit bank', 'bank_box');

        $this->bar($user, $home, 'Batangan 100 g (demo)', '100', [null]);
        $this->bar($user, $bank, 'Batangan 10 g (demo)', '10', ['DEMO-0001', 'DEMO-0002']);

        $this->jewelry($user, $home, 'Cincin kawin (demo)', 'cincin', ['gross_weight_g' => '8', 'stone_state' => 'known', 'stone_weight_g' => '2']);
        $this->jewelry($user, $home, 'Kalung polos (demo)', 'kalung', ['gross_weight_g' => '12.5', 'stone_state' => 'none', 'purity_input' => '70', 'purity_source' => 'percent', 'purity_fraction' => '0.700000']);
        // Stone weight unknown: shows "Data diperlukan" and stays out of the total.
        $this->jewelry($user, $home, 'Gelang (demo, batu belum ditimbang)', 'gelang', ['gross_weight_g' => '9.6', 'stone_state' => 'unknown']);
        // Held for someone else: excluded from every total.
        $this->jewelry($user, $bank, 'Liontin titipan (demo)', 'liontin', [
            'gross_weight_g' => '3', 'stone_state' => 'none', 'ownership' => 'held_for_others', 'held_for' => 'Kerabat (fiktif)',
        ]);
        // Old collection: owned-since is approximate.
        $this->jewelry($user, $home, 'Anting warisan (demo)', 'anting', [
            'gross_weight_g' => '4', 'stone_state' => 'none', 'acquisition_method' => 'inheritance',
            'owned_since' => '2008-01-01', 'owned_since_certainty' => 'approximate', 'owned_since_note' => 'Perkiraan, dari cerita keluarga (fiktif)',
        ]);
    }

    private function location(User $user, string $name, string $kind): Location
    {
        $location = new Location(['name' => $name, 'kind' => $kind]);
        $location->user_id = $user->id;
        $location->save();

        return $location;
    }

    /** @param  list<string|null>  $serials */
    private function bar(User $user, Location $location, string $name, string $unitWeight, array $serials): void
    {
        $item = new Item([
            'kind' => 'bar', 'name' => $name, 'location_id' => $location->id, 'unit_weight_g' => $unitWeight,
            'purity_source' => 'permil', 'purity_input' => '999.9', 'purity_fraction' => '0.999900',
            'ownership' => 'own', 'acquisition_method' => 'purchase', 'owned_since' => '2023-03-10', 'owned_since_certainty' => 'exact',
        ]);
        $item->user_id = $user->id;
        $item->save();

        foreach ($serials as $serial) {
            $item->units()->create(['serial_number' => $serial, 'quantity' => 1]);
        }
    }

    /** @param  array<string, mixed>  $attributes */
    private function jewelry(User $user, Location $location, string $name, string $type, array $attributes): void
    {
        $item = new Item($attributes + [
            'kind' => 'jewelry', 'name' => $name, 'jewelry_type' => $type, 'location_id' => $location->id,
            'purity_source' => 'permil', 'purity_input' => '750', 'purity_fraction' => '0.750000',
            'ownership' => 'own', 'purpose' => 'undecided', 'acquisition_method' => 'purchase',
            'owned_since' => '2022-08-01', 'owned_since_certainty' => 'exact',
        ]);
        $item->user_id = $user->id;
        $item->save();
    }
}
