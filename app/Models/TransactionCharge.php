<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionCharge extends Model
{
    protected $table = 'transaction_charges';

    protected $fillable = [
        'transaction_id',
        'other_charge_id',
        'code',
        'name',
        'amount',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function otherCharge()
    {
        return $this->belongsTo(OtherCharge::class, 'other_charge_id');
    }
}
