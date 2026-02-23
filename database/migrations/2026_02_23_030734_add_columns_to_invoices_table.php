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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('full_number')->nullable()->unique()->after('number');
            $table->date('delivery_date')->nullable()->after('due_date');
            $table->decimal('subtotal', 15, 2)->default(0)->after('delivery_date');
            $table->decimal('vat_total', 15, 2)->default(0)->after('subtotal');
            $table->decimal('total', 15, 2)->default(0)->after('vat_total');
            $table->string('currency', 3)->default('RON')->after('total');
            $table->enum('payment_method', ['numerar', 'ordin_plata', 'card', 'compensare'])->default('ordin_plata')->after('currency');
            $table->string('payment_reference')->nullable()->after('payment_method');
            $table->datetime('paid_at')->nullable()->after('payment_reference');
            $table->string('efactura_id')->nullable()->after('paid_at');
            $table->string('efactura_status')->nullable()->after('efactura_id');
            $table->string('pdf_path')->nullable()->after('efactura_status');

            // Make series/number properly typed (they may be nullable from stub)
            $table->string('series')->nullable()->change();
            $table->unsignedInteger('number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'full_number', 'delivery_date', 'subtotal', 'vat_total', 'total',
                'currency', 'payment_method', 'payment_reference', 'paid_at',
                'efactura_id', 'efactura_status', 'pdf_path',
            ]);
        });
    }
};
