<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DeliveryPrice extends Model
{
    protected $table = 'delivery_prices';

    protected $fillable = [
        'website_id',
        'city_code',
        'district_code',
        'subdistrict_code',
        'price',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * The pricing level implied by which columns are filled.
     * Minimum level is "city".
     */
    public function getLevelAttribute(): string
    {
        if (!empty($this->subdistrict_code)) return 'subdistrict';
        if (!empty($this->district_code))    return 'district';
        return 'city';
    }

    public function scopeForWebsite($query, $websiteId)
    {
        return $query->where('website_id', $websiteId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /* ---- Region relations (glb_* use string codes as PKs) ---- */

    public function city()
    {
        return $this->belongsTo(\App\Models\GlbCity::class, 'city_code', 'city_code');
    }

    public function district()
    {
        return $this->belongsTo(\App\Models\GlbDistrict::class, 'district_code', 'district_code');
    }

    public function subdistrict()
    {
        return $this->belongsTo(\App\Models\GlbSubdistrict::class, 'subdistrict_code', 'subdistrict_code');
    }
}
