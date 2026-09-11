<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $table = 'services';

    protected $fillable = [
        'website_id',
        'service_name',
        'service_title',
        'service_subtitle',
        'service_description',
        'service_image',
        'service_icon',
        'remark',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * Scope: active services, in order.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('id');
    }
}
