<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A saved calculation, frozen: later edits to the collection never change it.
        Schema::create('zakat_periods', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();

            $table->date('valuation_date');
            $table->string('madhhab', 20); // hanafi | syafii
            $table->string('rule_version', 40);

            $table->date('haul_start')->nullable();
            $table->date('haul_end')->nullable();
            $table->string('haul_start_hijri', 30)->nullable();
            $table->string('haul_end_hijri', 30)->nullable();

            // Frozen copy of what contributed and how it was reasoned (arrays, see ZakatResult).
            $table->jsonb('contributions');
            $table->jsonb('reasons');
            $table->jsonb('steps');

            // Grams are kept at calculation precision (scale 10); rounding is for display only.
            $table->decimal('nisab_g', 20, 10);
            $table->decimal('rate', 7, 6);
            $table->decimal('eligible_g', 20, 10)->nullable(); // NULL when status is needs_review
            $table->decimal('price_per_gram', 18, 2)->nullable();
            $table->decimal('zakat_g', 20, 10)->nullable();
            $table->decimal('zakat_rupiah', 20, 2)->nullable();

            $table->string('status', 20); // complete | needs_review
            $table->timestamps();

            $table->index(['user_id', 'valuation_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zakat_periods');
    }
};
