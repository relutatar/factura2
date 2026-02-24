<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'client_id', 'contract_template_id', 'number',
        'signed_date', 'start_date', 'end_date', 'value', 'currency',
        'status', 'additional_attributes', 'notes',
    ];

    protected $casts = [
        'status'               => ContractStatus::class,
        'additional_attributes'=> 'array',
        'signed_date'          => 'date',
        'start_date'           => 'date',
        'end_date'             => 'date',
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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
