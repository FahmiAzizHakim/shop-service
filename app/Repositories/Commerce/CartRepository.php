<?php

namespace App\Repositories\Commerce;

use App\Models\Cart;
use App\Models\CartItem;
use App\Repositories\BaseRepository;

/**
 * Guest carts and their lines.
 *
 * A cart belongs to a browser, not to an account, so it is looked up by its
 * token. Minting that token and deciding what a line is worth are CartService's
 * -- this only stores what it is told.
 */
class CartRepository extends BaseRepository
{
    protected $model = Cart::class;

    public function findByToken($websiteId, string $token): ?Cart
    {
        return $this->forWebsite($websiteId)->where('cart_token', $token)->first();
    }

    /** The browser's cart, made on the spot when it has none yet. */
    public function firstOrCreateForToken($websiteId, string $token): Cart
    {
        return Cart::firstOrCreate(
            ['website_id' => $websiteId, 'cart_token' => $token],
            ['last_activity_at' => now()]
        );
    }

    /**
     * Stamp who last used the cart.
     *
     * forceFill because these are diagnostics the caller states rather than
     * input a visitor submitted, so they are deliberately outside $fillable.
     */
    public function touch(Cart $cart, array $attributes): Cart
    {
        $cart->forceFill($attributes)->save();

        return $cart;
    }

    public function items(Cart $cart)
    {
        return $cart->items;
    }

    public function sumQty(Cart $cart): int
    {
        return (int) $cart->items()->sum('qty');
    }

    /** Write one line, replacing whatever was stored under the same key. */
    public function saveItem(Cart $cart, string $itemKey, array $attributes): CartItem
    {
        return CartItem::updateOrCreate(
            ['cart_id' => $cart->id, 'item_key' => $itemKey],
            $attributes
        );
    }

    /** Whatever the browser no longer has is gone from the cart. */
    public function deleteItemsExcept(Cart $cart, array $keepKeys): void
    {
        CartItem::where('cart_id', $cart->id)->whereNotIn('item_key', $keepKeys)->delete();
    }

    /**
     * Empty the cart but keep the row, so the browser comes back to the same
     * cart rather than to a new one.
     */
    public function clearItems(Cart $cart): void
    {
        $cart->items()->delete();
    }
}
