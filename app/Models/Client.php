<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Scopes\CompanyScope;
use App\Enums\ClientType;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'type', 'name', 'cif', 'cnp', 'reg_com', 'address', 'city', 'county', 'phone', 'email', 'notes',
    ];

    protected $casts = [
        'type' => ClientType::class,
    ];

    public function contacts()
    {
        return $this->hasMany(Contact::class);
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
