<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionBenefit extends Model
{
    protected $table = 'transaction_benefits';

    protected $fillable = [
        'transaction_id',
        'package_id',
        'package_name',
        'benefit_name',
        'qty',
        'price',
        'discount',
        'subtotal',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'qty'      => 'integer',
        'price'    => 'decimal:2',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id');
    }
}
