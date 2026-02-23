<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMovement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'product_id', 'invoice_id', 'type',
        'quantity', 'unit_price', 'notes', 'moved_at',
    ];

    protected $casts = [
        'type'     => StockMovementType::class,
        'moved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function (self $model) {
            if (empty($model->company_id)) {
                $model->company_id = session('active_company_id');
            }
        });

        // Update product stock after every new movement
        static::created(function (StockMovement $movement) {
            $delta = $movement->type === StockMovementType::Iesire
                ? -abs((float) $movement->quantity)
                : abs((float) $movement->quantity);

            $movement->product()->withoutGlobalScopes()->increment('stock_quantity', $delta);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withoutGlobalScopes();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withoutGlobalScopes();
    }
}
