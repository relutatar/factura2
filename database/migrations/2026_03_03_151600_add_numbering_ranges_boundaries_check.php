<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE numbering_ranges ADD CONSTRAINT chk_numbering_ranges_bounds CHECK (start_number <= next_number AND next_number <= end_number + 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE numbering_ranges DROP CHECK chk_numbering_ranges_bounds');
    }
};
