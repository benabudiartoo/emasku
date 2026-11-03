<?php

namespace Tests\Unit\Concerns;

use App\Services\Zakat\ZakatCalculator;
use App\Services\Zakat\ZakatInput;
use App\Services\Zakat\ZakatItem;
use App\Services\Zakat\ZakatResult;

trait BuildsZakatInput
{
    protected function bar(string $id, ?string $pure, ?string $date = '2024-01-10', string $certainty = 'exact', string $ownership = 'own'): ZakatItem
    {
        return new ZakatItem($id, $id, 'bar', $ownership, null, $pure, $pure === null ? ['unit_weight_g'] : [], $date, $certainty);
    }

    protected function ring(string $id, ?string $pure, ?string $purpose, ?string $date = '2024-01-10', string $certainty = 'exact', string $ownership = 'own'): ZakatItem
    {
        return new ZakatItem($id, $id, 'jewelry', $ownership, $purpose, $pure, $pure === null ? ['stone_weight_g'] : [], $date, $certainty);
    }

    /** @param  list<ZakatItem>  $items */
    protected function calculate(string $madhhab, array $items, string $on = '2026-11-03', ?string $price = null, ?string $manualHaul = null): ZakatResult
    {
        return (new ZakatCalculator)->calculate(new ZakatInput($madhhab, $on, $items, $price, $manualHaul));
    }

    protected function assertSame0(string $expected, ?string $actual, string $message = ''): void
    {
        $this->assertNotNull($actual, $message ?: 'expected a number, got null');
        $this->assertSame(0, bccomp($expected, $actual, 10), $message ?: "expected {$expected}, got {$actual}");
    }

    /** @param  list<string>  $codes */
    protected function assertReasonCodes(array $codes, ZakatResult $result): void
    {
        $this->assertEqualsCanonicalizing($codes, array_values(array_unique(array_column($result->reasons, 'code'))));
    }
}
