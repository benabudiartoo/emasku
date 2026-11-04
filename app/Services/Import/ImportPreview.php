<?php

namespace App\Services\Import;

use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Holds what was read from an upload so the owner can review it, and saves the rows they chose.
 *
 * The preview lives in the cache under a key made of the owner's id and a random token, for 30 minutes, so another
 * owner's token simply finds nothing. Saving reads the rows from here, never from the browser, so the browser can only
 * say WHICH rows (by number) to import, not what they contain.
 */
class ImportPreview
{
    public const TTL_SECONDS = 1800;

    public function __construct(private ExcelItemImporter $importer) {}

    /** @param  array{sheets: array<string, list<array<string, mixed>>>, default: ?string}  $parsed */
    public function put(string $ownerId, array $parsed, string $filename): string
    {
        $token = Str::random(40);
        Cache::put($this->key($ownerId, $token), $parsed + ['filename' => $this->cleanFilename($filename)], self::TTL_SECONDS);

        return $token;
    }

    /** @return ?array{sheets: array<string, list<array<string, mixed>>>, default: ?string, filename: string} */
    public function get(string $ownerId, string $token): ?array
    {
        if (preg_match('/^[A-Za-z0-9]{40}$/', $token) !== 1) {
            return null;
        }

        return Cache::get($this->key($ownerId, $token));
    }

    public function forget(string $ownerId, string $token): void
    {
        if (preg_match('/^[A-Za-z0-9]{40}$/', $token) === 1) {
            Cache::forget($this->key($ownerId, $token));
        }
    }

    /**
     * Create the chosen rows of one sheet as the owner's jewelry items, all or nothing.
     *
     * @param  list<int>  $selected  row numbers the owner kept checked
     * @return array{created: int, skipped: int} skipped = rows that turned out to exist already since the preview
     */
    public function save(User $owner, string $token, string $sheet, array $selected): array
    {
        $data = $this->get($owner->id, $token);
        if ($data === null || ! isset($data['sheets'][$sheet])) {
            return ['created' => 0, 'skipped' => 0];
        }

        $chosen = array_flip(array_map('intval', $selected));
        $existing = $this->existingKeys($owner);
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($owner, $data, $sheet, $chosen, &$existing, &$created, &$skipped) {
            foreach ($data['sheets'][$sheet] as $row) {
                if (! isset($chosen[$row['row']]) || $row['status'] === 'error') {
                    continue;
                }

                $key = ExcelItemImporter::duplicateKey($row['name'], $row['owned_since'], $row['net_weight_g'], $row['acquisition_cost']);
                // A row the owner left as a clean "ok/check" but that has since appeared in the collection is skipped;
                // a row marked duplicate that they checked on purpose is imported.
                if ($row['status'] !== 'duplicate' && isset($existing[$key])) {
                    $skipped++;

                    continue;
                }

                $owner->items()->create($this->attributes($row, $data['filename']));
                $existing[$key] = true;
                $created++;
            }
        });

        $this->forget($owner->id, $token);

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** @return array<string, mixed> */
    private function attributes(array $row, string $filename): array
    {
        $notes = 'Diimpor dari Excel ('.$filename.').';
        if ($row['status'] === 'check' && $row['purity_text'] !== '') {
            $notes .= ' Kadar di berkas: '.$row['purity_text'].'.';
        }

        return [
            'kind' => 'jewelry',
            'name' => $row['name'],
            'purchase_store' => $row['store'],
            'jewelry_type' => $row['jewelry_type'],
            'ownership' => 'own',
            'purity_source' => $row['purity_source'],
            'purity_input' => $row['purity_input'],
            'purity_fraction' => $row['purity_fraction'],
            'acquisition_method' => 'purchase',
            'acquisition_cost' => $row['acquisition_cost'],
            'owned_since' => $row['owned_since'],
            'owned_since_certainty' => $row['owned_since'] === null ? 'unknown' : 'exact',
            // The weight in these sheets is the weight of the piece itself; the stone is not stated.
            'net_weight_g' => $row['net_weight_g'],
            'stone_state' => 'unknown',
            'purpose' => 'undecided',
            'resale_discount_percent' => $row['resale_discount_percent'],
            'notes' => mb_substr($notes, 0, 2000),
        ];
    }

    /** @return array<string, true> */
    private function existingKeys(User $owner): array
    {
        $calc = app(\App\Services\GoldCalculator::class);
        $keys = [];
        foreach (Item::where('user_id', $owner->id)->with('units')->get() as $item) {
            $net = $calc->netWeight($item);
            $keys[ExcelItemImporter::duplicateKey(
                $item->name,
                $item->owned_since?->format('Y-m-d'),
                $net->isComplete() ? $net->value : null,
                $item->acquisition_cost === null ? null : (string) $item->acquisition_cost,
            )] = true;
        }

        return $keys;
    }

    private function key(string $ownerId, string $token): string
    {
        return "item-import:{$ownerId}:{$token}";
    }

    /** The file name as shown and stored in a note: no path, no control characters, at most 100 characters. */
    private function cleanFilename(string $filename): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $filename))) ?? '';

        return mb_substr($name === '' ? 'berkas.xlsx' : $name, 0, 100);
    }
}
