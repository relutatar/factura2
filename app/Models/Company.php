<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'cif', 'reg_com', 'address', 'city', 'county',
        'iban', 'bank', 'logo', 'invoice_prefix', 'efactura_settings',
    ];

    protected $casts = [
        'efactura_settings' => 'array',
    ];
}
