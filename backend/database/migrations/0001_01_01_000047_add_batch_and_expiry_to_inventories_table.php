<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('minimum_quantity')
                ->constrained('stock_batches')->nullOnDelete();
            $table->date('expiry_date')->nullable()->after('batch_id');
            $table->integer('maximum_quantity')->nullable()->after('expiry_date');

            $table->index(['tenant_id', 'batch_id']);
            $table->index(['tenant_id', 'expiry_date']);
        });

        // Add FK for warehouse_stocks.batch_id (table created in 044, stock_batches in 045)
        Schema::table('warehouse_stocks', function (Blueprint $table) {
            $table->foreign('batch_id')->references('id')->on('stock_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_stocks', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'batch_id']);
            $table->dropIndex(['tenant_id', 'expiry_date']);
            $table->dropColumn(['batch_id', 'expiry_date', 'maximum_quantity']);
        });
    }
};
