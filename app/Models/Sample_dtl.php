<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sample_dtl extends Model
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

    protected $casts = [
        'sample_id' => 'integer',
        'inquire_mst_id' => 'integer',
        'inquire_dtls_id' => 'integer',
        'product_id' => 'integer',
        'size_id' => 'integer',
        'color_id' => 'integer',
        'unit_id' => 'integer',
        'qnty' => 'integer',
        'sample_status' => 'integer',
        'active_status' => 'integer',
    ];
}
