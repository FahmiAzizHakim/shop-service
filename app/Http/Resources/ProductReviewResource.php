<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One review as a visitor reads it: who, how many stars, what they said, and
 * the photos they attached.
 *
 * What is not here is the point of the class. transaction_id says which order
 * this came from and is nobody's business but the seller's -- published on a
 * product page it would leak that an order exists and invite guessing at it.
 * created_by is the buyer's email, for the same reason. The admin screen reads
 * the model itself and sees both; the public sees a name and a date.
 */
class ProductReviewResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'       => (int) $this->id,
            'name'     => $this->reviewer_name,
            'rating'   => (int) $this->rating,
            'text'     => $this->review_text,
            // Verified by construction: a review cannot exist without an
            // order, so this is a constant rather than a column -- and saying
            // it here is what lets a product page badge the review without
            // learning anything about the order behind it.
            'verified' => true,
            'images'   => $this->images->map(fn ($image) => [
                'id'  => (int) $image->id,
                // Absolute, via ASSET_URL -- which points at the gateway, the
                // one origin a browser can reach.
                'url' => asset($image->image_url),
            ])->values()->all(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
