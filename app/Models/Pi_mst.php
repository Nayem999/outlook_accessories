<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pi_mst extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function company_info(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'company_id', 'id')->select('id', 'uuid', 'name');
    }
    public function buyer_info(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'buyer_id', 'id')->select('id', 'uuid', 'name');
    }
    public function bank_info(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id', 'id')->select('id', 'uuid', 'name');
    }
    public function data_dtls(): HasMany
    {
        return $this->HasMany(Pi_dtl::class, 'pi_id')->where('active_status', 1);
    }
}
