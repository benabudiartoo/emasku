<?php

namespace App\Services\Export;

use Carbon\Carbon;

/**
 * Text formatting for exports, matching what the screens show (resources/js/lib/format.ts): Indonesian decimal
 * comma, thousands dots, and "Data diperlukan" wherever a value cannot be computed. Never turns null into zero.
 */
final class Fmt
{
    public const NEEDS = 'Data diperlukan';

    public const DISCLAIMER = 'Ini alat bantu hitung, bukan fatwa. Untuk kepastian, konsultasikan dengan lembaga zakat atau ulama yang Anda percaya.';

    private const PURITY_UNIT = ['permil' => '‰', 'percent' => '%', 'karat' => 'karat'];

    private const MISSING = [
        'unit_weight_g' => 'berat per keping',
        'quantity' => 'jumlah keping',
        'gross_weight_g' => 'berat kotor',
        'stone_weight_g' => 'berat batu',
        'purity_fraction' => 'kadar',
    ];

    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** '4.5000' -> '4,50'. Trims zeros down to $minDecimals; null stays empty. */
    public static function gram(?string $value, int $minDecimals = 2, string $whenNull = ''): string
    {
        if ($value === null) {
            return $whenNull;
        }
        [$int, $dec] = array_pad(explode('.', $value, 2), 2, '');
        $dec = rtrim($dec, '0');
        $dec = str_pad($dec, $minDecimals, '0');
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $int);

        return $dec !== '' ? "{$grouped},{$dec}" : $grouped;
    }

    public static function rupiah(?string $value, string $whenNull = ''): string
    {
        return $value === null ? $whenNull : 'Rp '.self::gram($value, 0);
    }

    /** '0.025000' -> '2,5' (a rate as a percentage, without floating point). */
    public static function percent(string $rate): string
    {
        $dec = str_pad(rtrim(explode('.', $rate)[1] ?? '', '0'), 2, '0');
        $whole = (int) substr($dec, 0, 2);
        $frac = substr($dec, 2);

        return $frac !== '' ? "{$whole},{$frac}" : (string) $whole;
    }

    public static function date(?string $value): string
    {
        if (! $value) {
            return '';
        }
        [$y, $m, $d] = explode('-', substr($value, 0, 10));

        return (int) $d.' '.self::MONTHS[(int) $m - 1].' '.$y;
    }

    public static function purity(?string $source, ?string $input): string
    {
        if (! $source || $input === null) {
            return 'Kadar belum diisi';
        }

        return self::gram($input, 0).' '.(self::PURITY_UNIT[$source] ?? $source);
    }

    /** @param  list<string>  $missing */
    public static function missing(array $missing): string
    {
        return implode(', ', array_map(fn (string $m) => self::MISSING[$m] ?? $m, $missing));
    }

    public static function kind(string $kind): string
    {
        return $kind === 'bar' ? 'Batangan' : 'Perhiasan';
    }

    /** Decimal numbers inside the engine's step text, in Indonesian format; dates and versions are left alone. */
    public static function stepText(string $text): string
    {
        return preg_replace_callback(
            '/Rp\s\d[\d.]*|(?<![\d-])\d+\.\d+(?![\d-])/',
            fn (array $m) => str_starts_with($m[0], 'Rp') ? $m[0] : self::gram($m[0], 0),
            $text,
        );
    }

    /** One line for the result of a zakat calculation (a ZakatPresenter array). Needs-review is never a number. */
    public static function zakatOutcome(array $view): string
    {
        if ($view['status'] === 'needs_review') {
            return 'Perlu peninjauan';
        }

        return match ($view['outcome']) {
            'due' => 'Wajib zakat',
            'below_nisab' => 'Belum mencapai nisab',
            'haul_incomplete' => 'Haul belum genap',
            default => 'Perlu peninjauan',
        };
    }

    /** The zakat amount in grams, or why there is none. */
    public static function zakatGrams(array $view): string
    {
        if ($view['status'] === 'needs_review') {
            return 'Perlu peninjauan';
        }

        return $view['zakat_g'] === null ? 'Belum wajib' : self::gram($view['zakat_g']);
    }

    /** The zakat amount in rupiah, or why there is none. */
    public static function zakatRupiah(array $view): string
    {
        if ($view['status'] === 'needs_review') {
            return 'Perlu peninjauan';
        }
        if ($view['zakat_g'] === null) {
            return 'Belum wajib';
        }

        return $view['zakat_rupiah'] === null ? 'Harga belum diisi' : self::rupiah($view['zakat_rupiah']);
    }

    public static function today(): string
    {
        return Carbon::now()->toDateString();
    }
}
