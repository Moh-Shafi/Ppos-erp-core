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
        });

        // Add 'refunded' to status enum
        DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('success', 'pending', 'failed', 'refunded') DEFAULT 'success'");

        // Unique constraints for race-condition-safe idempotency.
        // MySQL allows multiple NULLs in a unique index, so nullable columns are safe.
        // Only non-NULL values must be unique per tenant.
        DB::statement('CREATE UNIQUE INDEX payments_tenant_idempotency_key_unique ON payments (tenant_id, idempotency_key)');
        DB::statement('CREATE UNIQUE INDEX payments_tenant_payment_reference_unique ON payments (tenant_id, payment_reference)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX payments_tenant_payment_reference_unique ON payments');
        DB::statement('DROP INDEX payments_tenant_idempotency_key_unique ON payments');

        DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('success', 'pending', 'failed') DEFAULT 'success'");

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('idempotency_key');
        });
    }
};
