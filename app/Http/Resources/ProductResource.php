<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A catalogue product with its images, sizes and specifications.
 *
 * price_from / price_to span the variants, so a card can show a range without
 * walking them; price 0 means "on request".
 */
class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        $prices = $this->variants->map(
            fn ($v) => (float) ($v->variant_price ?? $this->products_price)
        );

        return [
            'id'          => (int) $this->id,
            'service_id'  => (int) $this->service_id,
            'name'        => $this->products_name,
            'code'        => $this->products_code,
            'description' => $this->products_description,
            'price'       => (float) $this->products_price,
            'price_from'  => (float) ($prices->count() ? $prices->min() : $this->products_price),
            'price_to'    => (float) ($prices->count() ? $prices->max() : $this->products_price),
            'on_request'  => (float) $this->products_price <= 0 && $prices->max() <= 0,
            'dimensions'  => array_filter([
                'weight' => $this->products_weight,
                'width'  => $this->products_width,
                'length' => $this->products_length,
                'height' => $this->products_height,
            ], fn ($v) => $v !== null),
            'images'      => $this->images->map(fn ($i) => [
                'id'          => (int) $i->id,
                'url'         => asset($i->image_url),
                'description' => $i->image_description,
            ])->values(),
            // Present only where the query asked for it (withCount), so a
            // read that does not care about the stats does not pay for it.
            'views'       => $this->when(!is_null($this->views_count), fn () => (int) $this->views_count),
            'variants'       => ProductVariantResource::collection($this->variants),
            'specifications' => $this->specifications->map(fn ($s) => [
                'attribute' => $s->attribute,
                'value'     => $s->value,
            ])->values(),
        ];
    }
}
