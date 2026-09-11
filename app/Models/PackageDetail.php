<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageDetail extends Model
{
    protected $table = 'package_details';

    // Table only has created_at (no updated_at).
    public $timestamps = false;

    protected $fillable = [
        'package_id',
        'product_id',
        'other_charge_id',
        'other_benefit',
        'qty',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function otherCharge()
    {
        return $this->belongsTo(OtherCharge::class, 'other_charge_id');
    }

    /**
     * Which of the three kinds this line is: product | charge | benefit.
     */
    public function getLineTypeAttribute(): string
    {
        if ($this->product_id) {
            return 'product';
        }

        if ($this->other_charge_id) {
            return 'charge';
        }

        return 'benefit';
    }

    /**
     * Human label for the line, whatever kind it is.
     */
    public function getLineLabelAttribute(): string
    {
        return match ($this->line_type) {
            'product' => optional($this->product)->products_name ?: '(deleted product)',
            'charge'  => optional($this->otherCharge)->name ?: '(deleted charge)',
            default   => (string) $this->other_benefit,
        };
    }
}
