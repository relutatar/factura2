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
        Schema::create('numbering_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->enum('document_type', ['factura', 'chitanta', 'aviz', 'proforma']);
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('series', 20);
            $table->unsignedInteger('start_number');
            $table->unsignedInteger('end_number');
            $table->unsignedInteger('next_number');
            $table->string('work_point_code', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['company_id', 'document_type', 'series', 'fiscal_year', 'work_point_code'],
                'numbering_ranges_unique_scope'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('numbering_ranges');
    }
};
