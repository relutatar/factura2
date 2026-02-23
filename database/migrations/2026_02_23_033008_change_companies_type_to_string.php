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
        // Change from MySQL ENUM (rigid) to VARCHAR so new company types
        // only require adding a case to App\Enums\CompanyType — no migration needed.
        Schema::table('companies', function (Blueprint $table) {
            $table->string('type', 50)->default('ddd')->change();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->enum('type', ['ddd', 'paintball'])->default('ddd')->change();
        });
    }
};
