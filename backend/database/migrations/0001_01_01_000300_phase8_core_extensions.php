<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // products: add is_service column
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_service')->default(false)->after('is_active');
        });

        // sale_items: add metadata JSON column
        Schema::table('sale_items', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('total');
        });

        // sales: add optional context FKs
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('table_id')->nullable()->after('customer_id');
            $table->unsignedBigInteger('appointment_id')->nullable()->after('table_id');
            $table->index('table_id');
            $table->index('appointment_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['table_id']);
            $table->dropIndex(['appointment_id']);
            $table->dropColumn(['table_id', 'appointment_id']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_service');
        });
    }
};
