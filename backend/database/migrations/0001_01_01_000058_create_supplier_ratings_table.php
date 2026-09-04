<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('rating');
            $table->enum('criteria', ['quality', 'delivery', 'pricing', 'service', 'overall']);
            $table->text('note')->nullable();
            $table->foreignId('rated_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'supplier_id'], 'idx_sr_supplier');
            $table->index(['tenant_id', 'supplier_id', 'criteria'], 'idx_sr_supplier_criteria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_ratings');
    }
};
