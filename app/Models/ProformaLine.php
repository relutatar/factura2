<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProformaLine extends Model
{
    protected $fillable = [
        'proforma_id', 'product_id', 'description', 'quantity', 'unit',
        'unit_price', 'vat_rate_id', 'vat_amount', 'line_total', 'total_with_vat', 'sort_order',
    ];

    protected $casts = [
        'quantity'       => 'decimal:3',
        'unit_price'     => 'decimal:2',
        'vat_amount'     => 'decimal:2',
        'line_total'     => 'decimal:2',
        'total_with_vat' => 'decimal:2',
    ];

    public function proforma(): BelongsTo
    {
        return $this->belongsTo(Proforma::class);
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
