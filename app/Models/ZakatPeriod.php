<?php

namespace App\Models;

use App\Services\Zakat\ZakatResult;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A frozen copy of one zakat calculation. Decimals stay strings (no floats).
 */
class ZakatPeriod extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = ['id', 'user_id']; // the owner is always set from the session, never from input

    protected function casts(): array
    {
        return [
            'valuation_date' => 'date:Y-m-d',
            'haul_start' => 'date:Y-m-d',
            'haul_end' => 'date:Y-m-d',
            'contributions' => 'array',
            'reasons' => 'array',
            'steps' => 'array',
        ];
    }

    /**
     * A frozen, unsaved copy of a calculation for one owner. The owner comes from the caller (the session),
     * never from input; everything else is copied as it was, so later edits to the collection cannot change it.
     */
    public static function fromResult(ZakatResult $result, string $userId): self
    {
        $period = new self([
            'valuation_date' => $result->valuationDate,
            'madhhab' => $result->madhhab,
            'rule_version' => $result->ruleVersion,
            'haul_start' => $result->haulStart,
            'haul_end' => $result->haulEnd,
            'haul_start_hijri' => $result->haulStartHijri,
            'haul_end_hijri' => $result->haulEndHijri,
            'contributions' => $result->contributions,
            'reasons' => $result->reasons,
            'steps' => $result->steps,
            'nisab_g' => $result->nisabG,
            'rate' => $result->rate,
            'eligible_g' => $result->eligibleG,
            'price_per_gram' => $result->pricePerGram,
            'zakat_g' => $result->zakatG,
            'zakat_rupiah' => $result->zakatRupiah,
            'status' => $result->status,
        ]);
        $period->user_id = $userId;

        return $period;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
