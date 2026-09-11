<?php

namespace App\Http\Controllers\Api;

use App\Services\Commerce\CartService;
use Illuminate\Http\Request;

/**
 * The basket a guest builds before checking out.
 *
 * Keyed on the cart_token cookie, so there is no login: the browser that
 * saved a cart is the one that gets it back. Prices are never taken from the
 * request -- only keys and quantities are, and the lines come back re-priced
 * from the catalogue.
 */
class CartController extends ApiController
{
    protected $carts;

    public function __construct(CartService $carts)
    {
        $this->carts = $carts;
    }

    /**
     * The saved cart, re-priced. Also mints the cart cookie, so the first add
     * already has a cart to go to.
     */
    public function show($website)
    {
        $this->carts->tokenOrMint();

        return $this->lines((int) $website);
    }

    /**
     * Replace the saved cart with the posted basket. Unknown keys are dropped.
     */
    public function save(Request $request, $website)
    {
        $data = $request->validate([
            'items'       => 'present|array|max:' . CartService::MAX_LINES,
            'items.*.id'  => 'required|string|max:60',
            'items.*.qty' => 'nullable|integer|min:1|max:' . CartService::MAX_QTY,
        ]);

        $this->carts->save((int) $website, $data['items']);

        return $this->lines((int) $website, true);
    }

    public function clear($website)
    {
        $this->carts->clear((int) $website);

        return response()->json(['data' => ['items' => [], 'count' => 0], 'saved' => true]);
    }

    private function lines(int $websiteId, bool $saved = false)
    {
        $lines = $this->carts->displayLines($websiteId);

        return response()->json([
            'data'  => [
                'items' => $lines,
                'count' => array_sum(array_column($lines, 'qty')),
            ],
            'saved' => $saved,
        ]);
    }
}
