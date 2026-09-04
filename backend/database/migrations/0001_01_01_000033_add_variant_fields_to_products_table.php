<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('has_variants')->default(false)->after('is_active');
            $table->boolean('is_trackable')->default(true)->after('has_variants');
            $table->unsignedInteger('min_stock')->nullable()->after('is_trackable');
            $table->foreignId('base_unit_id')->nullable()->constrained('units')->nullOnDelete()->after('min_stock');
            $table->foreignId('purchase_unit_id')->nullable()->constrained('units')->nullOnDelete()->after('base_unit_id');
        });

        // Add indexes separately to avoid issues with FK constraints
        Schema::table('products', function (Blueprint $table) {
            $table->index(['tenant_id', 'has_variants']);
            $table->index(['tenant_id', 'is_trackable']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'has_variants']);
            $table->dropIndex(['tenant_id', 'is_trackable']);
            $table->dropForeign(['base_unit_id']);
            $table->dropForeign(['purchase_unit_id']);
            $table->dropColumn(['has_variants', 'is_trackable', 'min_stock', 'base_unit_id', 'purchase_unit_id']);
        });
    }
};
