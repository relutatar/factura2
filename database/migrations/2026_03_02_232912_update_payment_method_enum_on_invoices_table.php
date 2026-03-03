<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: add virament_bancar to enum alongside existing values
        DB::statement("ALTER TABLE invoices MODIFY COLUMN payment_method ENUM('virament_bancar','numerar','ordin_plata','card','compensare') NOT NULL DEFAULT 'virament_bancar'");

        // Step 2: migrate legacy values
        DB::table('invoices')
            ->whereIn('payment_method', ['ordin_plata', 'card', 'compensare'])
            ->update(['payment_method' => 'virament_bancar']);

        // Step 3: remove legacy values from enum
        DB::statement("ALTER TABLE invoices MODIFY COLUMN payment_method ENUM('virament_bancar','numerar') NOT NULL DEFAULT 'virament_bancar'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE invoices MODIFY COLUMN payment_method ENUM('virament_bancar','numerar','ordin_plata','card','compensare') NOT NULL DEFAULT 'virament_bancar'");
    }
};
