<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('kind', 30)->default('other'); // home | safe | bank_box | other
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });

        Schema::create('items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10); // bar | jewelry
            $table->string('name', 160);
            $table->string('brand', 120)->nullable();
            $table->string('jewelry_type', 40)->nullable();
            $table->string('package_condition', 120)->nullable();

            // Ownership is separate from location. held_for_others is excluded from every total.
            $table->string('ownership', 20)->default('own'); // own | held_for_others
            $table->string('held_for', 160)->nullable();

            // Weights in grams. NULL means unknown, never zero.
            $table->decimal('unit_weight_g', 14, 4)->nullable(); // bar: weight per piece
            $table->decimal('gross_weight_g', 14, 4)->nullable(); // jewelry
            $table->string('stone_state', 10)->nullable(); // none | known | unknown (NULL = not stated, treated as unknown)
            $table->decimal('stone_weight_g', 14, 4)->nullable();
            $table->decimal('net_weight_g', 14, 4)->nullable(); // measured net metal weight when provided

            // Purity stored as a fraction plus how it was stated.
            $table->decimal('purity_fraction', 7, 6)->nullable();
            $table->string('purity_source', 10)->nullable(); // permil | percent | karat
            $table->decimal('purity_input', 9, 4)->nullable(); // original number the owner typed

            $table->string('purpose', 20)->nullable(); // worn | investment | undecided (jewelry)

            $table->string('acquisition_method', 20)->default('purchase'); // purchase | gift | inheritance | opening_balance
            $table->decimal('acquisition_cost', 18, 2)->nullable(); // NULL = tidak diketahui

            // Input date is created_at. Owned-since and haul-start are separate, each with certainty and note.
            $table->date('owned_since')->nullable();
            $table->string('owned_since_certainty', 12)->default('exact'); // exact | approximate | unknown
            $table->text('owned_since_note')->nullable();
            $table->date('haul_start')->nullable();
            $table->string('haul_start_certainty', 12)->nullable();
            $table->text('haul_start_note')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'kind']);
        });

        Schema::create('item_units', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('item_id')->constrained()->cascadeOnDelete();
            $table->string('serial_number', 120)->nullable(); // NULL = part of an identical lot
            $table->unsignedInteger('quantity')->default(1); // serial piece = 1, lot = N
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained()->cascadeOnDelete();
            $table->string('path'); // random name on the private disk
            $table->string('original_name', 255);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('item_units');
        Schema::dropIfExists('items');
        Schema::dropIfExists('locations');
    }
};
