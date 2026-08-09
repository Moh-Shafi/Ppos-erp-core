<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->after('payment_reference');
            $table->index(['tenant_id', 'idempotency_key']);
        });

        // Add 'refunded' to status enum
        DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('success', 'pending', 'failed', 'refunded') DEFAULT 'success'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('success', 'pending', 'failed') DEFAULT 'success'");

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
