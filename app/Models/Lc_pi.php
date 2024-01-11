<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lc_pi extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function pi_info(): BelongsTo
    {
        return $this->belongsTo(Pi_mst::class,'pi_mst_id','id');
    }
}
