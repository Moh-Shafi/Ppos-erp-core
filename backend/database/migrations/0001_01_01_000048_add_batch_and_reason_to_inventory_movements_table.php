<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('note')
                ->constrained('stock_batches')->nullOnDelete();
            $table->foreignId('reason_id')->nullable()->after('batch_id')
                ->constrained('stock_adjustment_reasons')->nullOnDelete();

            $table->index(['tenant_id', 'batch_id']);
            $table->index(['tenant_id', 'reason_id']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'batch_id']);
            $table->dropIndex(['tenant_id', 'reason_id']);
            $table->dropColumn(['batch_id', 'reason_id']);
        });
    }
};
