<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 50);
            $table->string('settlement_id', 255);
            $table->decimal('gross_amount', 15, 2);
            $table->decimal('platform_fee', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->timestamp('settled_at')->nullable();
            $table->string('status', 50)->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'settlement_id']);
            $table->index('payment_id');
            $table->index('settled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settlements');
    }
};
