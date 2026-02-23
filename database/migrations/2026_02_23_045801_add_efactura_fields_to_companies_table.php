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
            $table->string('efactura_certificate_path')->nullable()->after('logo');
            $table->string('efactura_certificate_password')->nullable()->after('efactura_certificate_path');
            $table->boolean('efactura_test_mode')->default(true)->after('efactura_certificate_password');
            $table->string('efactura_cif')->nullable()->after('efactura_test_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'efactura_certificate_path',
                'efactura_certificate_password',
                'efactura_test_mode',
                'efactura_cif',
            ]);
        });
    }
};
