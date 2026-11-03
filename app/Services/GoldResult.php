<?php

namespace App\Services;

/**
 * A calculation outcome. `value` is a bcmath string, or null when data is missing (never "0" for missing).
 */
final class GoldResult
{
    public const COMPLETE = 'complete';

    public const NEEDS_DATA = 'needs_data';

    /** @param list<string> $missing field names that block the calculation */
    private function __construct(
        public readonly ?string $value,
        public readonly string $status,
        public readonly array $missing = [],
    ) {}

    public static function complete(string $value): self
    {
        return new self($value, self::COMPLETE);
    }

    /** @param list<string> $missing */
    public static function needsData(array $missing): self
    {
        return new self(null, self::NEEDS_DATA, array_values(array_unique($missing)));
    }

    public function isComplete(): bool
    {
        return $this->status === self::COMPLETE;
    }
}
