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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft'); // draft, trimisa, platita, anulata
            $table->string('series')->nullable();
            $table->unsignedInteger('number')->nullable();
            $table->string('full_number')->nullable()->unique();
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('vat_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('currency', 3)->default('RON');
            $table->enum('payment_method', ['numerar', 'ordin_plata', 'card', 'compensare'])->default('ordin_plata');
            $table->string('payment_reference')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('efactura_id')->nullable();
            $table->string('efactura_status')->nullable();
            $table->string('pdf_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
