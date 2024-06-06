<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'party_type_id' => 'integer',
        'party_id' => 'integer',
        'purpose_id' => 'integer',
        'amount' => 'float',
        'active_status' => 'integer',
    ];
}
