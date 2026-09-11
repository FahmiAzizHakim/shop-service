<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionAddress extends Model
{
    protected $table = 'transaction_addresses';

    protected $fillable = [
        'transaction_id',
        'recipient_name',
        'recipient_phone',
        'province_code',
        'city_code',
        'district_code',
        'subdistrict_code',
        'province_name',
        'city_name',
        'district_name',
        'subdistrict_name',
        'address_detail',
        'postal_code',
        'delivery_fee',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'delivery_fee' => 'decimal:2',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    /**
     * One-line formatted address (detail, subdistrict, district, city).
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_detail,
            $this->subdistrict_name,
            $this->district_name,
            $this->city_name,
            $this->province_name,
            $this->postal_code,
        ]);

        return implode(', ', $parts);
    }
}
