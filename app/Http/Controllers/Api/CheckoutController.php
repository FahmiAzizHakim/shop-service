<?php

namespace App\Http\Controllers\Api;

use App\Services\Checkout\CheckoutService;
use Illuminate\Http\Request;

/**
 * Placing an order. Public: a guest checks out with an address, not a login.
 *
 * Two steps against the same payload:
 *
 *   POST .../checkout/confirm   price it and show what will be charged
 *   POST .../checkout           place it, and answer with the receipt token
 *
 * confirm is not a prerequisite -- place re-prices from the catalogue itself,
 * so a client that skips it still cannot choose its own total.
 */
class CheckoutController extends ApiController
{
    protected $checkout;

    public function __construct(CheckoutService $checkout)
    {
        $this->checkout = $checkout;
    }

    /**
     * Price the basket without writing anything: the line items, the package
     * discounts, the resolved delivery fare and the total.
     */
    public function confirm(Request $request, $website)
    {
        $data  = $request->validate($this->checkout->rules($request));
        $order = $this->checkout->build((int) $website, $data);

        if (is_string($order)) {
            // build() answers with the reason the basket cannot be priced.
            return response()->json(['message' => $order], 422);
        }

        return response()->json([
            'data' => [
                'items'    => $order['items'],
                'subtotal' => $order['subtotal'],
                'discount' => $order['discount'],
                'fare'     => $order['fare'],
                // What the chosen payment method adds, itemised. Today that is
                // the QRIS admin fee and nothing else, but it is a list
                // because transaction_charges is one -- and because a total
                // with an unexplained difference in it is what a customer
                // queries.
                'charges'  => array_map(fn ($charge) => [
                    'code'   => $charge['code'],
                    'name'   => $charge['name'],
                    'amount' => (float) $charge['amount'],
                ], $order['charges']),
                'charge_total'   => (float) $order['chargeTotal'],
                'payment_method' => $order['method'],
                'total'    => $order['total'],
                'address'  => $order['address'],
            ],
        ]);
    }

    /**
     * Place the order and empty the basket. The receipt token in the response
     * is the only thing needed to read the order back.
     */
    public function place(Request $request, $website)
    {
        $data  = $request->validate($this->checkout->rules($request));
        $order = $this->checkout->build((int) $website, $data);

        if (is_string($order)) {
            return response()->json(['message' => $order], 422);
        }

        $result = $this->checkout->place((int) $website, $order);

        if ($result['status'] !== 'success') {
            return response()->json(['message' => $result['message']], 422);
        }

        $trx = $result['data'];

        return response()->json([
            'message' => $result['message'],
            'data'    => [
                'id'            => $trx->id,
                'receipt_token' => $trx->receipt_token,
                'total'         => (float) $trx->grandtotal,
            ],
        ], 201);
    }
}
