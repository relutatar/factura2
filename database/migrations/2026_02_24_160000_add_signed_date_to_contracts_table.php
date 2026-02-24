<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('contracts', 'signed_date')) {
            Schema::table('contracts', function (Blueprint $table): void {
                $table->date('signed_date')->nullable()->after('number');
            });
        }

        DB::table('contracts')
            ->whereNull('signed_date')
            ->whereNotNull('start_date')
            ->update(['signed_date' => DB::raw('start_date')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('contracts', 'signed_date')) {
            Schema::table('contracts', function (Blueprint $table): void {
                $table->dropColumn('signed_date');
            });
        }
    }
};
