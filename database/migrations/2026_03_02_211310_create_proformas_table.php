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
        Schema::create('proformas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            // FK to generated invoice – added without constraint to avoid circular dependency
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->enum('status', ['draft', 'trimisa', 'convertita', 'anulata'])->default('draft');
            // Numerotare (alocată de InvoiceService::reserveNextNumber la emitere)
            $table->string('series')->nullable();
            $table->unsignedInteger('number')->nullable();
            $table->string('full_number')->nullable();
            $table->foreignId('numbering_range_id')->nullable()->constrained('numbering_ranges')->nullOnDelete();
            // Date document
            $table->date('issue_date');
            $table->date('valid_until')->nullable();
            // Totaluri
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('vat_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('currency', 3)->default('RON');
            $table->string('pdf_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'full_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proformas');
    }
};
