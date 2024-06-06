<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trans_purpose extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'active_status' => 'integer',
    ];
}
