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
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('company_type_id')
                ->nullable()
                ->after('name')
                ->constrained('company_types')
                ->nullOnDelete();

            $table->dropColumn('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['company_type_id']);
            $table->dropColumn('company_type_id');
            $table->string('type', 50)->nullable()->after('name');
        });
    }
};
