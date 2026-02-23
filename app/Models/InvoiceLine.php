<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    protected $fillable = [
        'invoice_id', 'product_id', 'description', 'quantity', 'unit',
        'unit_price', 'vat_rate_id', 'vat_amount', 'line_total', 'total_with_vat', 'sort_order',
    ];

    protected $casts = [
        'quantity'       => 'decimal:3',
        'unit_price'     => 'decimal:2',
        'vat_amount'     => 'decimal:2',
        'line_total'     => 'decimal:2',
        'total_with_vat' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes();
    }

    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }
}
