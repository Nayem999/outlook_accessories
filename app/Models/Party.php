<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Party extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'party_type_id' => 'integer',
        'account_type' => 'integer',
        'opening_balance' => 'float',
        'trans_id' => 'integer',
        'active_status' => 'integer',
    ];
}
