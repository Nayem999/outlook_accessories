<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemporaryTbl extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'dr_amount' => 'float',
        'cr_amount' => 'float',
        'entry_form' => 'integer',
        'ext_key' => 'integer',
    ];
}
