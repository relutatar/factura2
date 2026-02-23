<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'client_id', 'type', 'number', 'title',
        'start_date', 'end_date', 'value', 'currency', 'billing_cycle',
        'status', 'ddd_frequency', 'ddd_locations',
        'paintball_sessions', 'paintball_players', 'notes',
    ];

    protected $casts = [
        'type'          => ContractType::class,
        'status'        => ContractStatus::class,
        'billing_cycle' => BillingCycle::class,
        'ddd_locations' => 'array',
        'start_date'    => 'date',
        'end_date'      => 'date',
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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
