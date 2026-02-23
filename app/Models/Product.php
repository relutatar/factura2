<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'code', 'name', 'description', 'unit',
        'unit_price', 'vat_rate_id', 'stock_quantity', 'stock_minimum',
        'category', 'is_active',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'unit_price'     => 'decimal:2',
        'stock_quantity' => 'decimal:3',
        'stock_minimum'  => 'decimal:3',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function (self $model) {
            if (empty($model->company_id)) {
                $model->company_id = session('active_company_id');
            }
        });
    }

    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * Returns true when current stock is at or below the minimum threshold.
     */
    public function isLowStock(): bool
    {
        return (float) $this->stock_quantity <= (float) $this->stock_minimum;
    }
}
