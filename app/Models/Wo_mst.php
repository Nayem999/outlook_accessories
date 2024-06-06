<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wo_mst extends Model
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
    public function supplier_info(): BelongsTo
    {
        return $this->belongsTo(Party::class,'supplier_id','id')->select('id', 'name');;
    }
    public function order_info(): BelongsTo
    {
        return $this->belongsTo(Order_mst::class,'order_id','id')->select('id','order_no');
    }
    public function data_dtls(): HasMany
    {
        return $this->HasMany(Wo_dtl::class,'wo_id')->where('active_status', 1);
    }

    protected $casts = [
        'company_id' => 'integer',
        'buyer_id' => 'integer',
        'supplier_id' => 'integer',
        'order_id' => 'integer',
        'currency_id' => 'integer',
        'active_status' => 'integer',
    ];
}
