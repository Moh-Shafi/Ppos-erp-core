<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('city', 100)->nullable()->after('address');
            $table->string('province', 100)->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('province');
            $table->string('email', 255)->nullable()->after('phone');
            $table->boolean('is_headquarters')->default(false)->after('is_active');
            $table->json('settings')->nullable()->after('is_headquarters');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['city', 'province', 'postal_code', 'email', 'is_headquarters', 'settings']);
        });
    }
};
