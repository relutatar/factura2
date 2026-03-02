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
        Schema::create('decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('decision_template_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number')->nullable();
            $table->unsignedSmallInteger('decision_year')->nullable();
            $table->date('decision_date')->nullable();
            $table->string('title');
            $table->enum('status', ['draft', 'issued', 'cancelled', 'archived'])->default('draft');
            $table->text('notes')->nullable();
            $table->string('legal_representative_name');
            $table->json('custom_attributes')->nullable();
            $table->longText('content_snapshot')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'decision_year', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('decisions');
    }
};
