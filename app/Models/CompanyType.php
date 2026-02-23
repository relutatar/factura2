<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Companies that belong to this type.
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Returns [id => name] array for use in Filament Select fields.
     */
    public static function selectOptions(): array
    {
        return self::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
