<?php

namespace App\Services\Zakat;

/**
 * One madhhab's rule sheet, read from config/zakat_rules.php. Rules are data, not branches in the calculator,
 * so a rule can be reviewed, referenced, and versioned on its own.
 */
final class ZakatRuleSet
{
    /**
     * @param  list<array{name: string, url: string, accessed: string, note?: string}>  $references
     */
    private function __construct(
        public readonly string $madhhab,
        public readonly string $label,
        public readonly string $version,
        public readonly string $nisabG,
        public readonly string $rate,
        public readonly bool $wornJewelryZakatable,
        public readonly ?bool $additionsJoinHaul,
        public readonly array $references,
    ) {}

    /** @return list<string> madhhab keys that have a complete, referenced rule sheet; nothing else is offered */
    public static function supported(): array
    {
        return array_values(array_filter(
            array_keys(config('zakat_rules.madhhabs', [])),
            fn (string $madhhab) => self::isComplete(config("zakat_rules.madhhabs.{$madhhab}")),
        ));
    }

    public static function for(string $madhhab): self
    {
        $sheet = config("zakat_rules.madhhabs.{$madhhab}");

        if (! is_array($sheet) || ! self::isComplete($sheet)) {
            throw new UnsupportedMadhhabException("No complete rule sheet for madhhab: {$madhhab}");
        }

        return new self(
            madhhab: $madhhab,
            label: $sheet['label'],
            version: $sheet['version'],
            nisabG: $sheet['nisab_g'],
            rate: $sheet['rate'],
            wornJewelryZakatable: $sheet['worn_jewelry_zakatable'],
            additionsJoinHaul: $sheet['additions_join_haul'],
            references: $sheet['references'],
        );
    }

    /**
     * Complete means every rule is present and at least one reference backs it. A sheet missing either is not
     * offered, so a madhhab label never appears without documented rules behind it.
     */
    private static function isComplete(array $sheet): bool
    {
        foreach (['label', 'version', 'nisab_g', 'rate', 'worn_jewelry_zakatable', 'references'] as $key) {
            if (! array_key_exists($key, $sheet) || $sheet[$key] === null || $sheet[$key] === []) {
                return false;
            }
        }

        // additions_join_haul may be null on purpose (unverified): the calculator then asks for review.
        return array_key_exists('additions_join_haul', $sheet);
    }
}
