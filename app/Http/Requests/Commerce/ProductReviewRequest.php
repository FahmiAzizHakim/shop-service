<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A buyer submitting a review from their receipt.
 *
 * Public: there is no login behind this, and the receipt token in the URL is
 * what says the caller is the buyer. So this validates shape only -- whether
 * the order may be reviewed, whether the product was on it, and whether it has
 * been reviewed already are all decided by ProductReviewService against the
 * transaction the token resolved to, where they can be answered once.
 *
 * Note what is deliberately absent: transaction_id. The order is named by the
 * token, never by the body, so there is no field here for a caller to point at
 * somebody else's purchase.
 */
class ProductReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level: the receipt token is the credential, and the gateway
        // rate limits this endpoint as it does the other public writes.
        return true;
    }

    public function rules(): array
    {
        return [
            // Which product on the order. That it is on the order is checked
            // against the transaction, not here.
            'product_id' => 'required|integer|min:1',

            'rating'     => 'required|integer|min:1|max:5',

            // Optional: a rating on its own is a review. Plenty of buyers give
            // five stars and no words, and refusing that would cost the
            // rating too.
            'review_text' => 'nullable|string|max:5000',

            // Optional override of the name the order carries, for a buyer who
            // would rather not be shown as they were billed.
            'reviewer_name' => 'nullable|string|max:191',

            /*
             * The photos. Multipart, so the gateway forwards the parts rather
             * than re-encoding them (see ServiceProxy::multipart).
             *
             * Capped at six, and at 5MB each to match the payment-proof
             * upload: this is the one endpoint on the storefront where a
             * stranger's files land on the seller's disk, and a cap is the
             * only thing standing between a review form and a free file host.
             */
            'images'   => 'nullable|array|max:6',
            'images.*' => 'file|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ];
    }

    public function attributes(): array
    {
        return [
            'product_id'    => 'product',
            'review_text'   => 'review',
            'reviewer_name' => 'name',
            'images.*'      => 'photo',
        ];
    }

    public function messages(): array
    {
        return [
            'rating.min'   => 'Please give a rating from 1 to 5.',
            'rating.max'   => 'Please give a rating from 1 to 5.',
            'images.max'   => 'Please attach at most 6 photos.',
            'images.*.max' => 'Each photo must be 5MB or smaller.',
        ];
    }
}
