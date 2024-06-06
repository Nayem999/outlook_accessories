<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Export_contract extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'lc_id' => 'integer',
        'active_status' => 'integer',
    ];
}
