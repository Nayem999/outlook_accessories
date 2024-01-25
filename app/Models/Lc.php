<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lc extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function company_info(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'company_id', 'id')->select('id', 'name');
    }
    public function buyer_info(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'buyer_id', 'id')->select('id', 'name', 'address', 'bin_no');
    }
    public function opening_bank_info(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'opening_bank_id', 'id')->select('id', 'name', 'branch', 'address', 'bin_no','swift_code');
    }
    public function advising_bank_info(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'advising_bank_id', 'id')->select('id', 'name', 'branch', 'address');
    }
    public function data_dtls(): HasMany
    {
        return $this->HasMany(Lc_pi::class, 'lc_id')->where('active_status', 1);
    }

    public function contract_dtls(): HasMany
    {
        return $this->HasMany(Export_contract::class, 'lc_id')->where('active_status', 1);
    }
}
