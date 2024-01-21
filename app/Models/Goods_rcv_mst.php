<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goods_rcv_mst extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function supplier_info(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'supplier_id', 'id')->select('id', 'name');;
    }
    public function wo_info(): BelongsTo
    {
        return $this->belongsTo(Wo_mst::class, 'wo_id', 'id')->select('id', 'wo_no');
    }
    public function data_dtls(): HasMany
    {
        return $this->HasMany(Goods_rcv_dtl::class, 'goods_rcv_id')->where('active_status', 1);
    }

}
