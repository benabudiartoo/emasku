<?php

namespace App\Services\Zakat;

use App\Models\ZakatPeriod;
use App\Services\GoldCalculator;

/**
 * Turns a calculation (fresh ZakatResult or stored ZakatPeriod) into one array shape for the pages.
 * Grams are rounded here, for display only; decimals stay strings and null never becomes zero.
 */
class ZakatPresenter
{
    public static function fromResult(ZakatResult $r): array
    {
        return self::build([
            'id' => null,
            'madhhab' => $r->madhhab,
            'rule_version' => $r->ruleVersion,
            'rule_outdated' => false,
            'valuation_date' => $r->valuationDate,
            'status' => $r->status,
            'outcome' => $r->outcome,
            'nisab_g' => $r->nisabG,
            'rate' => $r->rate,
            'eligible_g' => $r->eligibleG,
            'shortfall_g' => $r->shortfallG,
            'haul_start' => $r->haulStart,
            'haul_end' => $r->haulEnd,
            'haul_start_hijri' => $r->haulStartHijri,
            'haul_end_hijri' => $r->haulEndHijri,
            'price_per_gram' => $r->pricePerGram,
            'zakat_g' => $r->zakatG,
            'zakat_rupiah' => $r->zakatRupiah,
            'contributions' => $r->contributions,
            'reasons' => $r->reasons,
            'steps' => $r->steps,
            'references' => $r->references,
        ]);
    }

    public static function fromPeriod(ZakatPeriod $p): array
    {
        $outcome = self::outcomeOf($p);

        // The period does not store references, so they come from today's rule sheet; flag it when that sheet
        // has changed since the calculation (or the madhhab is no longer offered).
        $references = [];
        $outdated = true;
        try {
            $rules = ZakatRuleSet::for($p->madhhab);
            $references = $rules->references;
            $outdated = $rules->version !== $p->rule_version;
        } catch (UnsupportedMadhhabException) {
        }

        return self::build([
            'id' => $p->id,
            'madhhab' => $p->madhhab,
            'rule_version' => $p->rule_version,
            'rule_outdated' => $outdated,
            'valuation_date' => $p->valuation_date->format('Y-m-d'),
            'status' => $p->status,
            'outcome' => $outcome,
            'nisab_g' => $p->nisab_g,
            'rate' => $p->rate,
            'eligible_g' => $p->eligible_g,
            'shortfall_g' => $outcome === ZakatResult::BELOW_NISAB ? bcsub($p->nisab_g, $p->eligible_g, GoldCalculator::SCALE) : null,
            'haul_start' => $p->haul_start?->format('Y-m-d'),
            'haul_end' => $p->haul_end?->format('Y-m-d'),
            'haul_start_hijri' => $p->haul_start_hijri,
            'haul_end_hijri' => $p->haul_end_hijri,
            'price_per_gram' => $p->price_per_gram,
            'zakat_g' => $p->zakat_g,
            'zakat_rupiah' => $p->zakat_rupiah,
            'contributions' => $p->contributions,
            'reasons' => $p->reasons,
            'steps' => $p->steps,
            'references' => $references,
        ]);
    }

    /** The small shape for lists and the summary card. */
    public static function summary(ZakatPeriod $p): array
    {
        $full = self::fromPeriod($p);

        return [
            'id' => $full['id'],
            'valuation_date' => $full['valuation_date'],
            'madhhab_label' => $full['madhhab_label'],
            'status' => $full['status'],
            'outcome' => $full['outcome'],
            'eligible_g' => $full['eligible_g'],
            'zakat_g' => $full['zakat_g'],
            'zakat_rupiah' => $full['zakat_rupiah'],
        ];
    }

    /** What the form and the explanation modal offer: only madhhab with a complete rule sheet. */
    public static function madhhabs(): array
    {
        return array_map(function (string $key) {
            $rules = ZakatRuleSet::for($key);

            return [
                'key' => $key,
                'label' => $rules->label,
                'version' => $rules->version,
                'nisab_g' => $rules->nisabG,
                'worn_jewelry_zakatable' => $rules->wornJewelryZakatable,
                'references' => $rules->references,
            ];
        }, ZakatRuleSet::supported());
    }

    /** A stored period keeps no `outcome` column; it follows from the stored figures. */
    private static function outcomeOf(ZakatPeriod $p): ?string
    {
        if ($p->status !== ZakatResult::COMPLETE) {
            return null;
        }
        if ($p->zakat_g !== null) {
            return ZakatResult::DUE;
        }

        return bccomp($p->eligible_g, $p->nisab_g, GoldCalculator::SCALE) < 0
            ? ZakatResult::BELOW_NISAB
            : ZakatResult::HAUL_INCOMPLETE;
    }

    private static function build(array $a): array
    {
        $g = fn (?string $v) => $v === null ? null : GoldCalculator::round($v, 4);

        try {
            $label = ZakatRuleSet::for($a['madhhab'])->label;
        } catch (UnsupportedMadhhabException) {
            $label = ucfirst($a['madhhab']);
        }

        $a['madhhab_label'] = $label;
        foreach (['eligible_g', 'shortfall_g', 'zakat_g'] as $key) {
            $a[$key] = $g($a[$key]);
        }
        $a['contributions'] = array_map(fn (array $c) => [...$c, 'pure_gold_g' => $g($c['pure_gold_g'])], $a['contributions']);
        // Step figures are not shown (the text already carries them), so they are not sent.
        $a['steps'] = array_map(fn (array $s) => ['rule' => $s['rule'], 'text' => $s['text']], $a['steps']);

        return $a;
    }
}
