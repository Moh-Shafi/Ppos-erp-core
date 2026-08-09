<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();

            $table->enum('payment_method', ['cash', 'qris', 'card', 'bank_transfer']);
            $table->decimal('amount', 15, 2);
            $table->decimal('change_amount', 15, 2)->default(0);

            // For gateway integration (QRIS/Midtrans)
            $table->string('payment_reference')->nullable();
            $table->enum('status', ['success', 'pending', 'failed'])->default('success');
            $table->json('metadata')->nullable();

            $table->dateTime('payment_date');

            $table->timestamps();

            $table->index(['tenant_id', 'sale_id']);
            $table->index(['tenant_id', 'payment_method']);
            $table->index('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
