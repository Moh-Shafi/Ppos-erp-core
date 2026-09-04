<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('held_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->json('cart_data');
            $table->string('hold_number');
            $table->enum('status', ['held', 'recalled', 'expired'])->default('held');
            $table->dateTime('held_at');
            $table->dateTime('recalled_at')->nullable();
            $table->dateTime('expires_at');

            $table->timestamps();

            $table->unique(['tenant_id', 'hold_number']);
            $table->index(['tenant_id', 'store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('held_sales');
    }
};
