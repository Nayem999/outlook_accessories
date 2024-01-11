<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function bank_info(): BelongsTo
    {
        return $this->belongsTo(Bank::class,'bank_id','id')->select('id', 'name');
    }
    public function party_info(): BelongsTo
    {
        return $this->belongsTo(Party::class,'party_id','id')->select('id', 'name');
    }
    public function trans_purpose_info(): BelongsTo
    {
        return $this->belongsTo(Trans_purpose::class,'trans_purpose_id','id')->select('id', 'name');
    }

}
