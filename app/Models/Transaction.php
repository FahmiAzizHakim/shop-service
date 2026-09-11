<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $table = 'transactions';

    protected $fillable = [
        'website_id',
        'receipt_no',
        'receipt_token',
        'transaction_date',
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'price',
        'discount',
        'delivery_fee',
        'charges',
        'total',
        'tax',
        'grandtotal',
        'payment_type',
        'status',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'price'            => 'decimal:2',
        'discount'         => 'decimal:2',
        'delivery_fee'     => 'decimal:2',
        'charges'          => 'decimal:2',
        'total'            => 'decimal:2',
        'tax'              => 'decimal:2',
        'grandtotal'       => 'decimal:2',
    ];

    /* ---- Relations ---- */

    public function details()
    {
        return $this->hasMany(TransactionDetail::class, 'transaction_id');
    }

    // Package lines: these carry the price/discount; what a package contains
    // is exploded into details/chargeItems/benefits at price 0.
    public function packages()
    {
        return $this->hasMany(TransactionPackage::class, 'transaction_id');
    }

    // Non-priced perks that came with a package.
    public function benefits()
    {
        return $this->hasMany(TransactionBenefit::class, 'transaction_id');
    }

    public function address()
    {
        return $this->hasOne(TransactionAddress::class, 'transaction_id');
    }

    // Named chargeItems (not charges) to avoid colliding with the `charges`
    // decimal column that mirrors the sum of these rows.
    public function chargeItems()
    {
        return $this->hasMany(TransactionCharge::class, 'transaction_id');
    }

    public function histories()
    {
        return $this->hasMany(TransactionHist::class, 'transaction_id')->orderByDesc('id');
    }

    public function attachments()
    {
        return $this->hasMany(TransactionAttachment::class, 'transaction_id')->orderByDesc('id');
    }

    /**
     * The STS code record for this transaction's status.
     */
    public function statusCode()
    {
        return $this->belongsTo(Code::class, 'status', 'code');
    }

    /**
     * The TRT code record for the payment type.
     */
    public function paymentTypeCode()
    {
        return $this->belongsTo(Code::class, 'payment_type', 'code');
    }

    /* ---- Scopes ---- */

    public function scopeForWebsite($query, $websiteId)
    {
        return $query->where('website_id', $websiteId);
    }

    /* ---- Helpers ---- */

    /**
     * Human-readable status name from the codes table (falls back to the code).
     */
    public function getStatusNameAttribute(): string
    {
        return optional($this->statusCode)->name ?? (string) $this->status;
    }
}
