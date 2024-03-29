<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Doc_acpt_dtl extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function doc_info(): BelongsTo
    {
        return $this->belongsTo(Document::class,'doc_id','id')->select('id', 'name');;
    }
}
