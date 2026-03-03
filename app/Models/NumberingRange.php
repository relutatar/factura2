<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NumberingRange extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'decision_id',
        'document_type',
        'fiscal_year',
        'series',
        'start_number',
        'end_number',
        'next_number',
        'work_point_code',
        'is_active',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'start_number' => 'integer',
        'end_number' => 'integer',
        'next_number' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function (self $model): void {
            if (empty($model->company_id)) {
                $model->company_id = session('active_company_id');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(Decision::class);
    }
}
