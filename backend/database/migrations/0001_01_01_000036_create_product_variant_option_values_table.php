<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_id')->constrained('product_variant_options')->cascadeOnDelete();
            $table->string('value', 100);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['option_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_option_values');
    }
};
