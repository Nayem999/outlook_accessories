<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pi_dtl extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function product_info(): BelongsTo
    {
        return $this->belongsTo(Product::class,'product_id','id')->select('id', 'name', 'code');
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
    /* public function wo_info(): BelongsTo
    {
        return $this->belongsTo(Wo_mst::class,'wo_id','id')->select('id', 'wo_no');
    } */
    public function po_info(): BelongsTo
    {
        return $this->belongsTo(Order_mst::class,'order_id','id')->select('id', 'order_no');
    }

    protected $casts = [
        'pi_id' => 'integer',
        'order_id' => 'integer',
        'order_dtls_id' => 'integer',
        'product_id' => 'integer',
        'size_id' => 'integer',
        'color_id' => 'integer',
        'unit_id' => 'integer',
        'qnty' => 'integer',
        'price' => 'float',
        'amount' => 'float',
        'packing' => 'integer',
        'net_weight' => 'float',
        'gross_weight' => 'float',
        'active_status' => 'integer',
    ];

}
