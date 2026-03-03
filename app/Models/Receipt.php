<?php

namespace App\Models;

use App\Enums\ReceiptStatus;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'invoice_id',
        'status',
        'series',
        'number',
        'full_number',
        'numbering_range_id',
        'work_point_code',
        'issue_date',
        'amount',
        'currency',
        'received_by',
        'pdf_path',
        'notes',
    ];

    protected $casts = [
        'status' => ReceiptStatus::class,
        'issue_date' => 'date',
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function (self $model): void {
            if (empty($model->company_id)) {
                $model->company_id = session('active_company_id');
            }
        });

        static::updating(function (self $model): void {
            if ($model->isDirty(['series', 'number', 'full_number', 'numbering_range_id', 'work_point_code'])) {
                throw new \RuntimeException('Numerotarea chitanței este imutabilă după emitere.');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withoutGlobalScopes();
    }

    public function numberingRange(): BelongsTo
    {
        return $this->belongsTo(NumberingRange::class);
    }
}
