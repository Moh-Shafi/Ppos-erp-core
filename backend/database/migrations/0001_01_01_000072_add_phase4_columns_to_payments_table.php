<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'refund_amount')) {
                $table->decimal('refund_amount', 15, 2)->default(0)->after('change_amount');
            }
            if (!Schema::hasColumn('payments', 'refund_status')) {
                $table->enum('refund_status', ['none', 'partial', 'full'])->default('none')->after('refund_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['refund_amount', 'refund_status']);
        });
    }
};
