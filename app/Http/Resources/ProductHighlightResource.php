<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One highlight as the storefront reads it: a heading and the products under
 * it.
 *
 * The products are full ProductResources rather than ids, so a highlight row
 * renders from this payload alone -- the same cards the catalogue draws, with
 * the same prices and images, because they come from the same resource.
 */
class ProductHighlightResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'       => (int) $this->id,
            'name'     => $this->highlight_name,
            'products' => ProductResource::collection($this->products)->resolve(),
        ];
    }
}
