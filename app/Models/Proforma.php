<?php

namespace App\Models;

use App\Enums\ProformaStatus;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proforma extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'client_id', 'contract_id', 'invoice_id',
        'status', 'series', 'number', 'full_number', 'numbering_range_id',
        'issue_date', 'valid_until',
        'subtotal', 'vat_total', 'total', 'currency',
        'pdf_path', 'notes',
    ];

    protected $casts = [
        'status'      => ProformaStatus::class,
        'issue_date'  => 'date',
        'valid_until' => 'date',
        'subtotal'    => 'decimal:2',
        'vat_total'   => 'decimal:2',
        'total'       => 'decimal:2',
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

    // ─── Relationships ────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class)->withDefault();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withDefault();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProformaLine::class)->orderBy('sort_order');
    }

    public function numberingRange(): BelongsTo
    {
        return $this->belongsTo(NumberingRange::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isEditable(): bool
    {
        return $this->status === ProformaStatus::Draft;
    }
}
