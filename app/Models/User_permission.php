<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User_permission extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'role_id' => 'integer',
        'user_id' => 'integer',
        'view' => 'integer',
        'add' => 'integer',
        'edit' => 'integer',
    ];
}
