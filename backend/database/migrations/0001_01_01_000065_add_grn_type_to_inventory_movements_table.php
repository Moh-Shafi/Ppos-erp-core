<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE inventory_movements MODIFY COLUMN type ENUM('purchase','sale','sale_return','purchase_return','adjustment','transfer_in','transfer_out','initial','grn')");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE inventory_movements MODIFY COLUMN type ENUM('purchase','sale','sale_return','purchase_return','adjustment','transfer_in','transfer_out','initial')");
    }
};
