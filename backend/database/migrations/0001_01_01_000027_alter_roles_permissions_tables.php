<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->boolean('is_system')->default(true)->after('slug');
            $table->integer('sort_order')->default(0)->after('is_system');
            $table->index('tenant_id');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->foreignId('module_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index('module_id');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropIndex(['module_id']);
            $table->dropForeign(['module_id']);
            $table->dropColumn('module_id');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['tenant_id', 'is_system', 'sort_order']);
        });
    }
};
