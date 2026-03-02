<?php

namespace App\Models;

use App\Enums\DecisionStatus;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Decision extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'decision_template_id',
        'number',
        'decision_year',
        'decision_date',
        'title',
        'status',
        'notes',
        'legal_representative_name',
        'custom_attributes',
        'content_snapshot',
    ];

    protected $casts = [
        'status' => DecisionStatus::class,
        'decision_year' => 'integer',
        'decision_date' => 'date',
        'custom_attributes' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function (self $model): void {
            if (empty($model->company_id)) {
                $model->company_id = session('active_company_id');
            }

            if (blank($model->legal_representative_name)) {
                $company = Company::withoutGlobalScopes()->find($model->company_id);
                $model->legal_representative_name = $company?->administrator ?? '';
            }

            if (blank($model->title) && ! empty($model->decision_template_id)) {
                $template = DecisionTemplate::find($model->decision_template_id);
                $model->title = $template?->name ?? 'Decizie administrativă';
            }
        });

        static::updating(function (self $model): void {
            $wasIssued = ($model->getOriginal('status') instanceof DecisionStatus
                ? $model->getOriginal('status')
                : DecisionStatus::tryFrom((string) $model->getOriginal('status'))) === DecisionStatus::Issued;

            if (! $wasIssued) {
                return;
            }

            $immutable = [
                'number',
                'decision_year',
                'decision_date',
                'title',
                'legal_representative_name',
                'custom_attributes',
                'content_snapshot',
                'decision_template_id',
                'company_id',
            ];

            foreach ($immutable as $column) {
                if ($model->isDirty($column)) {
                    throw new \RuntimeException('Deciziile emise nu mai pot modifica câmpurile legale.');
                }
            }
        });

        static::created(function (self $model): void {
            DecisionAuditLog::log('decision_created', [
                'company_id' => $model->company_id,
                'decision_id' => $model->id,
                'decision_template_id' => $model->decision_template_id,
            ]);
        });

        static::updated(function (self $model): void {
            DecisionAuditLog::log('decision_updated', [
                'company_id' => $model->company_id,
                'decision_id' => $model->id,
                'decision_template_id' => $model->decision_template_id,
            ]);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DecisionTemplate::class, 'decision_template_id');
    }

    public function numberingRanges(): HasMany
    {
        return $this->hasMany(NumberingRange::class, 'decision_id');
    }
}
