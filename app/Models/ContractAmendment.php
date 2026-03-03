<?php

namespace App\Models;

use App\Enums\ContractAmendmentStatus;
use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractAmendment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'contract_id',
        'document_template_id',
        'amendment_number',
        'signed_date',
        'body',
        'content_snapshot',
        'attributes',
        'status',
        'pdf_path',
        'notes',
    ];

    protected $casts = [
        'status' => ContractAmendmentStatus::class,
        'attributes' => 'array',
        'signed_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function (self $model): void {
            if (empty($model->company_id)) {
                $model->company_id = session('active_company_id');
            }

            if (empty($model->amendment_number) && ! empty($model->contract_id)) {
                $model->amendment_number = (int) static::withoutGlobalScopes()
                    ->where('contract_id', $model->contract_id)
                    ->max('amendment_number') + 1;
            }
        });
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class)->withoutGlobalScopes();
    }

    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class)->withoutGlobalScopes();
    }

    public function getFullLabelAttribute(): string
    {
        $contractNumber = $this->contract?->number ?? '—';

        return 'Act adițional nr. ' . $this->amendment_number . ' la contractul ' . $contractNumber;
    }
}
