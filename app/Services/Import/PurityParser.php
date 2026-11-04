<?php

namespace App\Services\Import;

use App\Services\GoldCalculator;
use InvalidArgumentException;

/**
 * Reads purity as it is written in a spreadsheet: karat ("17K"), per mille ("750"), or percent ("75.5%"), alone or
 * together ("750/17K"). When a karat value and a per mille or percent value disagree by more than half a percentage
 * point, the karat value is used and the result is flagged as a conflict. Anything unreadable gives no purity (null),
 * never a guessed one.
 */
class PurityParser
{
    /** Two readings of the same purity may differ by this fraction (0.5 percentage points) before they count as a conflict. */
    private const TOLERANCE = '0.005';

    /**
     * @return array{source: ?string, input: ?string, fraction: ?string, conflict: bool, unreadable: bool}
     */
    public static function parse(?string $text): array
    {
        $none = ['source' => null, 'input' => null, 'fraction' => null, 'conflict' => false, 'unreadable' => false];

        $text = trim((string) $text);
        if ($text === '') {
            return $none; // nothing written: not an error, just not filled in
        }

        $unreadable = ['source' => null, 'input' => null, 'fraction' => null, 'conflict' => false, 'unreadable' => true];

        $readings = [];
        foreach (preg_split('/[\/|;]|\s+-\s+/u', str_replace(',', '.', mb_strtoupper($text))) ?: [] as $token) {
            $token = trim(str_replace(' ', '', $token));
            if ($token === '') {
                continue;
            }
            $reading = self::token($token);
            if ($reading === null) {
                return $unreadable;
            }
            $readings[] = $reading;
        }
        if ($readings === []) {
            return $unreadable;
        }

        $karat = null;
        $other = null;
        foreach ($readings as $r) {
            if ($r['source'] === 'karat') {
                $karat ??= $r;
            } else {
                $other ??= $r;
            }
        }

        $chosen = $karat ?? $other;
        $conflict = $karat !== null && $other !== null
            && bccomp(self::abs(bcsub($karat['fraction'], $other['fraction'], 10)), self::TOLERANCE, 10) > 0;

        return ['source' => $chosen['source'], 'input' => $chosen['input'], 'fraction' => $chosen['fraction'], 'conflict' => $conflict, 'unreadable' => false];
    }

    /** @return ?array{source: string, input: string, fraction: string} */
    private static function token(string $token): ?array
    {
        if (preg_match('/^(\d+(?:\.\d+)?)(K|KT|KARAT)$/', $token, $m)) {
            return self::reading('karat', $m[1]);
        }
        if (preg_match('/^(\d+(?:\.\d+)?)%$/', $token, $m)) {
            return self::reading('percent', $m[1]);
        }
        if (preg_match('/^(\d+(?:\.\d+)?)(‰|PERMIL)?$/u', $token, $m)) {
            // A bare number is per mille only when it is plainly one (above 100); a bare 75 could be anything.
            if (($m[2] ?? '') !== '' || bccomp($m[1], '100', 4) > 0) {
                return self::reading('permil', $m[1]);
            }
        }

        return null;
    }

    /** @return ?array{source: string, input: string, fraction: string} */
    private static function reading(string $source, string $input): ?array
    {
        try {
            return ['source' => $source, 'input' => $input, 'fraction' => GoldCalculator::purityFraction($source, $input)];
        } catch (InvalidArgumentException) {
            return null; // out of range for its unit
        }
    }

    private static function abs(string $v): string
    {
        return ltrim($v, '-') === '' ? '0' : ltrim($v, '-');
    }
}
