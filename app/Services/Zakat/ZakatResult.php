<?php

namespace App\Services\Zakat;

/**
 * Outcome of one calculation. Decimals are bcmath strings; null means "not known", never zero.
 *
 * status  complete      a figure can be given (including "not due")
 *         needs_review  data is missing or the case is not supported: no amount, `reasons` says why
 * outcome (complete only) due | below_nisab | haul_incomplete
 */
final class ZakatResult
{
    public const COMPLETE = 'complete';

    public const NEEDS_REVIEW = 'needs_review';

    public const DUE = 'due';

    public const BELOW_NISAB = 'below_nisab';

    public const HAUL_INCOMPLETE = 'haul_incomplete';

    /**
     * @param  list<array{item_id: string, name: string, kind: string, pure_gold_g: ?string, included: bool, reason: ?string}>  $contributions
     * @param  list<array{code: string, message: string, item_id?: string}>  $reasons
     * @param  list<array{rule: string, text: string, figures: array<string, mixed>, reference: ?string}>  $steps
     * @param  list<array{name: string, url: string, accessed: string, note?: string}>  $references
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $outcome,
        public readonly string $madhhab,
        public readonly string $ruleVersion,
        public readonly string $valuationDate,
        public readonly string $nisabG,
        public readonly string $rate,
        public readonly ?string $eligibleG,
        public readonly ?string $shortfallG,
        public readonly ?string $haulStart,
        public readonly ?string $haulEnd,
        public readonly ?string $haulStartHijri,
        public readonly ?string $haulEndHijri,
        public readonly ?string $pricePerGram,
        public readonly ?string $zakatG,
        public readonly ?string $zakatRupiah,
        public readonly array $contributions,
        public readonly array $reasons,
        public readonly array $steps,
        public readonly array $references,
    ) {}

    public function isComplete(): bool
    {
        return $this->status === self::COMPLETE;
    }

    public function isDue(): bool
    {
        return $this->outcome === self::DUE;
    }
}
