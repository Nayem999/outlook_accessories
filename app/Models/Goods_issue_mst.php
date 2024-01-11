<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goods_issue_mst extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function company_info(): BelongsTo
    {
        return $this->belongsTo(Party::class,'company_id','id')->select('id', 'name');;
    }
    public function buyer_info(): BelongsTo
    {
        return $this->belongsTo(Party::class,'buyer_id','id')->select('id', 'name');;
    }
    public function order_info(): BelongsTo
    {
        return $this->belongsTo(order_mst::class,'order_id','id')->select('id', 'order_no');
    }
    public function data_dtls(): HasMany
    {
        return $this->HasMany(Goods_issue_dtl::class,'goods_issue_id')->where('active_status', 1);
    }
}
