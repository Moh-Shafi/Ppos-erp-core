<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number', 100);
            $table->integer('quantity')->default(0);
            $table->date('received_date');
            $table->date('expiry_date')->nullable();
            $table->decimal('cost_price', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id', 'batch_number'], 'uniq_batch_product_number');
            $table->index(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
