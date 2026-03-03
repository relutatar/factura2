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
        'status', 'series', 'number', 'full_number', 'numbering_range_id', 'work_point_code',
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

        static::updating(function (self $model): void {
            $statusValue = $model->status instanceof ProformaStatus
                ? $model->status->value
                : (string) $model->status;

            if ($statusValue === ProformaStatus::Draft->value) {
                return;
            }

            if ($model->isDirty(['series', 'number', 'full_number', 'numbering_range_id', 'work_point_code'])) {
                throw new \RuntimeException('Numerotarea proformei nu mai poate fi modificată după emitere.');
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
