<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway_transaction_id', 255)->nullable()->after('idempotency_key');
            $table->string('gateway_status', 50)->nullable()->after('gateway_transaction_id');
            $table->json('gateway_response')->nullable()->after('gateway_status');
            $table->decimal('settlement_amount', 15, 2)->nullable()->after('refund_status');
            $table->decimal('platform_fee', 15, 2)->nullable()->after('settlement_amount');
            $table->decimal('net_amount', 15, 2)->nullable()->after('platform_fee');
            $table->timestamp('settled_at')->nullable()->after('net_amount');
            $table->timestamp('expires_at')->nullable()->after('settled_at');
            $table->string('gateway_account_id', 255)->nullable()->after('expires_at');

            $table->index('gateway_transaction_id');
            $table->index(['tenant_id', 'gateway_status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'gateway_transaction_id',
                'gateway_status',
                'gateway_response',
                'settlement_amount',
                'platform_fee',
                'net_amount',
                'settled_at',
                'expires_at',
                'gateway_account_id',
            ]);
            $table->dropIndex(['tenant_id', 'gateway_status']);
        });
    }
};
