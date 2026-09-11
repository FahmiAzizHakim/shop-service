<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $table = 'packages';

    protected $fillable = [
        'website_id',
        'service_id',
        'package_name',
        'package_code',
        'package_price',
        'package_discount',
        'package_description',
        'remark',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'package_price'    => 'decimal:2',
        'package_discount' => 'decimal:2',
        'is_active'        => 'boolean',
    ];

    public function details()
    {
        return $this->hasMany(PackageDetail::class, 'package_id')->orderBy('id');
    }

    /**
     * The service this package belongs to. Null = not tied to one service.
     */
    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * The service this package is listed under: its own service_id, or -- when
     * that was never set -- the service of the first product it contains. That
     * fallback is how the landing page can group packages into service tabs
     * before anyone has filled the field in.
     */
    public function getResolvedServiceIdAttribute(): ?int
    {
        if ($this->service_id) {
            return (int) $this->service_id;
        }

        foreach ($this->details as $detail) {
            if ($detail->product_id && $detail->product && $detail->product->service_id) {
                return (int) $detail->product->service_id;
            }
        }

        return null;
    }

    /**
     * The image shown on the package card: the first image of the first product
     * the package contains. Products without an image are skipped, so a package
     * whose first line has no photo still gets one from the next line.
     *
     * Eager-load details.product.images to keep this off the N+1 path.
     */
    public function getCoverImageAttribute(): ?string
    {
        foreach ($this->details as $detail) {
            if (!$detail->product_id || !$detail->product) {
                continue;
            }

            $image = $detail->product->images
                ->firstWhere('is_active', true)
                ?? $detail->product->images->first();

            if ($image && $image->image_url) {
                return $image->image_url;
            }
        }

        return null;
    }

    /**
     * Package price after discount.
     */
    public function getNetPriceAttribute()
    {
        return max(0, (float) $this->package_price - (float) $this->package_discount);
    }

    public function scopeForWebsite($query, $websiteId)
    {
        return $query->where('website_id', $websiteId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Packages of one service. Pass null to get the ones tied to no service.
     */
    public function scopeForService($query, $serviceId)
    {
        return is_null($serviceId)
            ? $query->whereNull('service_id')
            : $query->where('service_id', $serviceId);
    }
}
