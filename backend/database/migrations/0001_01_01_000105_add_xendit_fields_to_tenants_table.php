<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('xendit_user_id', 255)->nullable()->after('plan_id');
            $table->string('xendit_fee_rule_id', 255)->nullable()->after('xendit_user_id');

            $table->index('xendit_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['xendit_user_id', 'xendit_fee_rule_id']);
        });
    }
};
