<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'client_id', 'contract_id', 'type', 'status',
        'series', 'number', 'full_number', 'issue_date', 'due_date',
        'delivery_date', 'subtotal', 'vat_total', 'total', 'currency',
        'payment_method', 'payment_reference', 'paid_at',
        'efactura_id', 'efactura_status', 'pdf_path', 'notes',
    ];

    protected $casts = [
        'type'           => InvoiceType::class,
        'status'         => InvoiceStatus::class,
        'payment_method' => PaymentMethod::class,
        'issue_date'     => 'date',
        'due_date'       => 'date',
        'delivery_date'  => 'date',
        'paid_at'        => 'datetime',
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

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    /**
     * Returns true when the invoice is past due and not settled/cancelled.
     */
    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->isPast()
            && $this->status !== InvoiceStatus::Platita
            && $this->status !== InvoiceStatus::Anulata;
    }
}
