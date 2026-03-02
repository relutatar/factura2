<?php

namespace App\Models;

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
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(Decision::class);
    }
}
