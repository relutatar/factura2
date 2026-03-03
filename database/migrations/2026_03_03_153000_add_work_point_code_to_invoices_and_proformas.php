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
            $table->string('work_point_code', 20)
                ->nullable()
                ->after('numbering_range_id');
        });

        Schema::table('proformas', function (Blueprint $table): void {
            $table->string('work_point_code', 20)
                ->nullable()
                ->after('numbering_range_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proformas', function (Blueprint $table): void {
            $table->dropColumn('work_point_code');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('work_point_code');
        });
    }
};
