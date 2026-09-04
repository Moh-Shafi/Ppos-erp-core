<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->integer('points');
            $table->enum('type', ['earn', 'redeem', 'expire', 'adjust']);
            $table->enum('source', ['sale', 'manual', 'expiry_sweep']);
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->integer('balance_after');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id'], 'idx_loyalty_tx_customer');
            $table->index(['tenant_id', 'customer_id', 'created_at'], 'idx_loyalty_tx_customer_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_loyalty_transactions');
    }
};
