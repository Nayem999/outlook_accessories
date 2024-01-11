<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation_mst extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function company_info(): BelongsTo
    {
        return $this->belongsTo(Party::class,'company_id','id')->select('id', 'name');
    }
    public function buyer_info(): BelongsTo
    {
        return $this->belongsTo(Party::class,'buyer_id','id')->select('id', 'name');
    }
    public function inquire_info(): BelongsTo
    {
        return $this->belongsTo(Inquire_mst::class,'order_inquire_id','id')->select('id', 'inquire_no');
    }
    public function order_info(): BelongsTo
    {
        return $this->belongsTo(Order_mst::class,'order_inquire_id','id')->select('id', 'order_no');
    }

    /* public function inquire_info(): BelongsTo
    {
        return $this->belongsTo(Inquire_mst::class, 'order_inquire_id', 'id', function ($query) {
            $query->where('quotation_msts.quotation_type', 1);
        })->select('id', 'inquire_no');
    }

    public function order_info(): BelongsTo
    {
        return $this->belongsTo(Order_mst::class, 'order_inquire_id', 'id', function ($query) {
            $query->where('quotation_msts.quotation_type', 2);
        })->select('id', 'order_no');
    } */
    public function data_dtls(): HasMany
    {
        return $this->HasMany(Quotation_dtl::class,'quotation_id')->where('active_status', 1);
    }
}


