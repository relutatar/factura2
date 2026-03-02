<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DecisionTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'category',
        'body_template',
        'custom_fields_schema',
        'is_active',
    ];

    protected $casts = [
        'custom_fields_schema' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(function (self $model): void {
            DecisionAuditLog::log('decision_template_created', [
                'company_id' => $model->company_id,
                'decision_template_id' => $model->id,
            ]);
        });

        static::updated(function (self $model): void {
            DecisionAuditLog::log('decision_template_updated', [
                'company_id' => $model->company_id,
                'decision_template_id' => $model->id,
            ]);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class);
    }
}
