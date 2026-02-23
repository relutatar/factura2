<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'company_type_id', 'cif', 'reg_com', 'address', 'city', 'county',
        'iban', 'bank', 'logo', 'invoice_prefix', 'efactura_settings',
        'efactura_certificate_path', 'efactura_certificate_password',
        'efactura_test_mode', 'efactura_cif',
    ];

    protected $casts = [
        'efactura_settings'  => 'array',
        'efactura_test_mode' => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The type/category this company belongs to.
     */
    public function companyType(): BelongsTo
    {
        return $this->belongsTo(CompanyType::class);
    }

    /**
     * Users who have access to this company.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
