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
        Schema::table('numbering_ranges', function (Blueprint $table) {
            $table->foreignId('decision_id')
                ->nullable()
                ->after('company_id')
                ->constrained('decisions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('numbering_ranges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decision_id');
        });
    }
};
