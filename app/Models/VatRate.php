<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VatRate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'value', 'label', 'description', 'is_default', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'value'      => 'decimal:2',
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    /**
     * Returns options array for use in Filament Select fields.
     *
     * @return array<int, string>
     */
    public static function selectOptions(): array
    {
        return self::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('label', 'id')
            ->toArray();
    }

    /**
     * Returns the default VAT rate record.
     */
    public static function defaultRate(): ?self
    {
        return self::where('is_default', true)->where('is_active', true)->first();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }
}
