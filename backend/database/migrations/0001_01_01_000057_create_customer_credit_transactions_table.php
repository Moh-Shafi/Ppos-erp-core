<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['debit', 'credit', 'adjust']);
            $table->enum('source', ['sale', 'payment', 'manual']);
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('balance_after', 15, 2);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id'], 'idx_credit_tx_customer');
            $table->index(['tenant_id', 'customer_id', 'created_at'], 'idx_credit_tx_customer_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credit_transactions');
    }
};
