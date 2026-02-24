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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('company_type_id')
                ->nullable()
                ->constrained('company_types')
                ->nullOnDelete();
            $table->string('administrator');
            $table->string('cif')->unique();
            $table->string('reg_com')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('county')->nullable();
            $table->string('iban')->nullable();
            $table->string('bank')->nullable();
            $table->string('logo')->nullable();
            $table->string('efactura_certificate_path')->nullable();
            $table->string('efactura_certificate_password')->nullable();
            $table->boolean('efactura_test_mode')->default(true);
            $table->string('efactura_cif')->nullable();
            $table->json('efactura_settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
