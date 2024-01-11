<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doc_acpt_mst extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function data_dtls(): HasMany
    {
        return $this->hasMany(Doc_acpt_dtl::class,'doc_acpt_id')->where('active_status', 1);
    }
}
