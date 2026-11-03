<?php

namespace Tests\Feature\Concerns;

use App\Models\Item;
use App\Models\User;

/** Small collections with known pure gold, owned long enough (or not) for the haul. */
trait CreatesZakatCollections
{
    /** @return array<string, string> a valid preview/save payload for $madhhab */
    protected function inputs(string $madhhab = 'hanafi', array $over = []): array
    {
        return $over + ['date' => now()->toDateString(), 'madhhab' => $madhhab];
    }

    /** $pieces bars of $unit g at 999.9: pure gold = unit x pieces x 0.9999. */
    protected function bars(User $user, string $unit, int $pieces, ?string $ownedSince = null, array $over = []): Item
    {
        $item = Item::factory()->create($over + [
            'user_id' => $user->id,
            'unit_weight_g' => $unit,
            'owned_since' => $ownedSince ?? now()->subYears(3)->toDateString(),
            'owned_since_certainty' => 'exact',
        ]);
        $item->units()->create(['serial_number' => null, 'quantity' => $pieces]);

        return $item;
    }

    /** 99.99 g pure gold, owned for years: over both nisab, haul complete. */
    protected function dueBars(User $user): Item
    {
        return $this->bars($user, '10.0000', 10);
    }
}
