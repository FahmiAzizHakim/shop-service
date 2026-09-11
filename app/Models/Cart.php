<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A guest cart, identified by the cart_token cookie in the visitor's browser.
 */
class Cart extends Model
{
    protected $table = 'carts';

    protected $fillable = [
        'website_id',
        'cart_token',
        'user_id',
        'session_id',
        'ip',
        'user_agent',
        'last_activity_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(CartItem::class, 'cart_id')->orderBy('id');
    }

    public function scopeForWebsite($query, $websiteId)
    {
        return $query->where('website_id', $websiteId);
    }

    /**
     * Carts untouched since $days ago -- what a prune would delete.
     */
    public function scopeStale($query, $days = 30)
    {
        return $query->where('last_activity_at', '<', now()->subDays($days));
    }
}
