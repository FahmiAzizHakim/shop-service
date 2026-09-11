<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The QRIS admin fee
    |--------------------------------------------------------------------------
    |
    | Paying by QRIS costs the seller two things, and they become known at
    | different moments:
    |
    |   provider_fee   what Qrisly takes per payment. Known always: 100.
    |   the unique nudge   1..99, added by Qrisly to the amount so two payments
    |                      of equal value can be told apart. Known only once
    |                      the payment is generated -- which is after checkout.
    |
    | Charging the customer the sum of those would mean quoting a figure that
    | cannot be known while they are still filling in the form, and then
    | surcharging them a few rupiah for a "unique code" they never asked for.
    | That reads as a mystery charge, and it is the thing customers query.
    |
    | So the order quotes a flat `amount` (200) and the difference comes back as
    | a discount once the nudge is known:
    |
    |   quoted at checkout            200
    |   asked of Qrisly               grandtotal - (200 - 100)   i.e. less 100
    |   Qrisly adds the nudge         + 20        -> customer pays 120 of it
    |   discount recorded             100 - 20 = 80
    |   the order's admin cost        200 - 80 = 120              = 100 + 20
    |
    | The customer is quoted a round number and always pays *less* than quoted,
    | never more, and the receipt shows why: the charge and the discount are
    | two lines. See TransactionService::applyQrisAdjustment, which records the
    | discount, and thirdparty-service's QrisController, which does the
    | subtraction on the way to Qrisly.
    |
    | Two places can set the quoted amount, and a website's own row wins:
    |
    |   1. an other_charges row for that website with the code below -- which
    |      is admin-editable through the Other Charges screen, and the way to
    |      change the fee for one site or to switch it off (set it inactive)
    |   2. this default, when no such row exists -- so the fee is charged from
    |      the first order on a fresh site rather than being silently zero
    |
    | Set CHECKOUT_QRIS_FEE=0 to charge nothing anywhere by default.
    |
    | Manual transfer carries no fee: the buyer uploads proof of payment
    | instead, which costs nothing to accept.
    |
    */

    'qris_fee' => [
        // Looked up in other_charges by (website_id, code). Also the code
        // snapshotted onto the transaction_charges row.
        'code'   => env('CHECKOUT_QRIS_FEE_CODE', 'QRISFEE'),
        'name'   => env('CHECKOUT_QRIS_FEE_NAME', 'Biaya Admin QRIS'),
        // What the customer is quoted at checkout. Must be at least
        // provider_fee + 99, or the discount cannot cover the largest possible
        // nudge and the customer would end up paying more than quoted.
        'amount' => (float) env('CHECKOUT_QRIS_FEE', 200),

        /*
        | What Qrisly itself takes per payment, which is the part the seller
        | owes whatever happens. It bounds the discount: at most this much can
        | come back, and it is the figure subtracted before Qrisly is asked for
        | an amount.
        |
        | Not thirdparty-service's copy of the same number by accident -- that
        | service subtracts it on the way out, this one bounds what may be
        | given back. Both read it from their own config so neither has to ask
        | the other for a constant, and they are checked against each other by
        | the arithmetic: a mismatch shows up as a discount that does not land
        | on 100 + nudge.
        */
        'provider_fee' => (float) env('CHECKOUT_QRIS_PROVIDER_FEE', 100),

        // The line the discount is written as. A second transaction_charges
        // row with a negative amount, so the receipt can show the quote and
        // the refund side by side and neither is hidden inside a total.
        'discount_code' => env('CHECKOUT_QRIS_DISCOUNT_CODE', 'QRISDISC'),
        'discount_name' => env('CHECKOUT_QRIS_DISCOUNT_NAME', 'Diskon Biaya Admin QRIS'),
    ],

];
