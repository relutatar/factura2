<?php

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractAnnex extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'contract_id',
        'document_template_id',
        'title',
        'annex_code',
        'body',
        'content_snapshot',
        'attributes',
        'file_path',
        'file_original_name',
        'file_mime_type',
        'pdf_path',
        'notes',
    ];

    protected $casts = [
        'attributes' => 'array',
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

    public function isGenerated(): bool
    {
        return ! empty($this->document_template_id) || ! empty($this->body);
    }

    public function isFileAttachment(): bool
    {
        return ! empty($this->file_path);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class)->withoutGlobalScopes();
    }

    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class)->withoutGlobalScopes();
    }
}
