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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['mentenanta_ddd', 'eveniment_paintball']);
            $table->string('number');
            $table->string('title');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('value', 15, 2)->default(0);
            $table->string('currency', 3)->default('RON');
            $table->enum('billing_cycle', ['lunar', 'trimestrial', 'anual', 'unic'])->default('lunar');
            $table->enum('status', ['activ', 'suspendat', 'expirat', 'reziliat'])->default('activ');
            $table->string('ddd_frequency')->nullable();
            $table->json('ddd_locations')->nullable();
            $table->unsignedInteger('paintball_sessions')->nullable();
            $table->unsignedInteger('paintball_players')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
