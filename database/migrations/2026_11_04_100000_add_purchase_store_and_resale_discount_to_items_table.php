<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Where it was bought (free text); not the storage location.
            $table->string('purchase_store', 120)->nullable();
            // What the buyer is expected to take off when this item is sold, in percent (0 to 100). NULL = not set.
            $table->decimal('resale_discount_percent', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['purchase_store', 'resale_discount_percent']);
        });
    }
};
