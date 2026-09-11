<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtherCharge extends Model
{
    protected $table = 'other_charges';

    protected $fillable = [
        'website_id',
        'code',
        'name',
        'description',
        'remark',
        'price',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeForWebsite($query, $websiteId)
    {
        return $query->where('website_id', $websiteId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
