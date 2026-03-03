<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('numbering_range_id')
                ->nullable()
                ->after('full_number')
                ->constrained('numbering_ranges')
                ->nullOnDelete();

            $table->dropUnique('invoices_full_number_unique');
            $table->unique(['company_id', 'full_number'], 'invoices_company_full_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('invoices_company_full_number_unique');
            $table->unique('full_number', 'invoices_full_number_unique');
            $table->dropConstrainedForeignId('numbering_range_id');
        });
    }
};
