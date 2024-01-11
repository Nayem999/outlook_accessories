<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Maturity_payment extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function lc_info(): BelongsTo
    {
        return $this->belongsTo(Lc::class,'lc_id','id');
    }
}
