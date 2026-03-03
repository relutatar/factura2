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
        Schema::create('receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['emisa', 'anulata'])->default('emisa');
            $table->string('series', 20);
            $table->unsignedInteger('number');
            $table->string('full_number');
            $table->foreignId('numbering_range_id')->nullable()->constrained('numbering_ranges')->nullOnDelete();
            $table->string('work_point_code', 20)->nullable();
            $table->date('issue_date');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('RON');
            $table->string('received_by')->nullable();
            $table->string('pdf_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'full_number']);
            $table->unique('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
