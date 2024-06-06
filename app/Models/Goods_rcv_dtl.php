<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;


class Goods_rcv_dtl extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function product_info(): BelongsTo
    {
        return $this->belongsTo(Product::class,'product_id','id')->select('id', 'name');
    }
    public function color_info(): BelongsTo
    {
        return $this->belongsTo(Color::class,'color_id','id')->select('id', 'name');
    }
    public function size_info(): BelongsTo
    {
        return $this->belongsTo(Size::class,'size_id','id')->select('id', 'name');
    }
    public function unit_info(): BelongsTo
    {
        return $this->belongsTo(Unit::class,'unit_id','id')->select('id', 'name');
    }

    public function gd_rcv_info(): HasOne
    {
        return $this->hasOne(Goods_rcv_dtl::class, 'wo_dtls_id', 'wo_dtls_id')->select('wo_dtls_id', DB::raw('SUM(qnty) as gd_rcv_qnty'))->where('active_status', 1)->groupBy('wo_dtls_id');
    }
    public function wo_dtls_info(): BelongsTo
    {
        return $this->belongsTo(Wo_dtl::class,'wo_dtls_id','id')->select('id', 'qnty');
    }

    protected $casts = [
        'goods_rcv_id' => 'integer',
        'wo_dtls_id' => 'integer',
        'product_id' => 'integer',
        'size_id' => 'integer',
        'color_id' => 'integer',
        'unit_id' => 'integer',
        'qnty' => 'integer',
        'extra_qnty' => 'integer',
        'active_status' => 'integer',
    ];
}
