<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('hold_status', ['none', 'held', 'recalled'])->default('none')->after('payment_status');
            $table->dateTime('held_at')->nullable()->after('hold_status');
            $table->decimal('refunded_amount', 15, 2)->default(0)->after('change_amount');
            $table->enum('refund_status', ['none', 'partial', 'full'])->default('none')->after('refunded_amount');
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->nullOnDelete()->after('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['price_list_id']);
            $table->dropColumn(['hold_status', 'held_at', 'refunded_amount', 'refund_status', 'price_list_id']);
        });
    }
};
