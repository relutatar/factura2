<?php

namespace App\Models;

use App\Enums\ClientType;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'type', 'name', 'cif', 'cnp', 'reg_com', 'address', 'city', 'county', 'phone', 'email', 'notes',
    ];

    protected $casts = [
        'type' => ClientType::class,
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
        static::creating(function (self $model) {
            if (empty($model->company_id)) {
                $model->company_id = session('active_company_id');
            }
        });
    }
}
