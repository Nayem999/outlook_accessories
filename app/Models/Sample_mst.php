<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sample_mst extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function company_info(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'company_id', 'id')->select('id', 'name');
    }
    public function buyer_info(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'buyer_id', 'id')->select('id', 'name');
    }
    public function inquire_info(): BelongsTo
    {
        return $this->belongsTo(Inquire_mst::class, 'inquire_id', 'id')->select('id', 'inquire_no', 'delivery_req_date');
    }
    public function dtls_info(): HasMany
    {
        return $this->HasMany(Inquire_dtl::class, 'inquire_id')->where('active_status', 1);
    }

    public function sample_dtls(): HasMany
    {
        //  return $this->HasMany(Sample_dtl::class, 'sample_id')->where('active_status', 1)->select(['sample_id', 'sample_status'])->groupBy(['sample_id', 'sample_status']);
        // Define a subquery to get the latest 'id' for each 'product_id'
        $subquery = Sample_dtl::selectRaw('MAX(id) as id')
            ->where('active_status', 1)
            ->groupBy('product_id', 'sample_id', 'style','size_id','color_id','unit_id');

        // Join the main query with the subquery to filter the desired rows
        return $this->hasMany(Sample_dtl::class, 'sample_id')
            ->where('active_status', 1)
            ->whereIn('id', $subquery)
            ->select(['sample_id', 'sample_status']);
    }


    protected $casts = [
        'company_id' => 'integer',
        'buyer_id' => 'integer',
        'inquire_id' => 'integer',
        'active_status' => 'integer',
    ];
}
