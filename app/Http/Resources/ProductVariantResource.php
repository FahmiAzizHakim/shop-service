<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => (int) $this->id,
            'name'        => $this->variant_name,
            // Null means "inherit the product price"; resolved here so the
            // frontend never has to know that rule.
            'price'       => (float) ($this->variant_price ?? $this->product->products_price ?? 0),
            'description' => $this->variant_description,
            'dimensions'  => array_filter([
                'width'  => $this->variant_width,
                'length' => $this->variant_length,
                'height' => $this->variant_height,
                'weight' => $this->variant_weight,
            ], fn ($v) => $v !== null),
        ];
    }
}
