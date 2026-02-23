<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'client_id', 'name', 'phone', 'email', 'position', 'notes',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
