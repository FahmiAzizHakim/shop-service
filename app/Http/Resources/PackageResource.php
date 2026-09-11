<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A package with its net price and what it contains.
 *
 * Contents come back as typed lines (product / charge / benefit) plus a plain
 * label, so a frontend can render the feature list without joining anything.
 */
class PackageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => (int) $this->id,
            'service_id'  => $this->service_id ? (int) $this->service_id : null,
            // The service the card is filed under: service_id, or the service
            // of the first product when nobody has set it. The packages
            // section groups its tabs by this, not by service_id.
            'resolved_service_id' => $this->resolved_service_id,
            'name'        => $this->package_name,
            'code'        => $this->package_code,
            'description' => $this->package_description,
            'price'       => (float) $this->package_price,
            'discount'    => (float) $this->package_discount,
            'net_price'   => (float) $this->net_price,
            'cover_image' => $this->cover_image ? asset($this->cover_image) : null,
            'contents'    => $this->details->map(fn ($d) => [
                'type'  => $d->line_type,           // product | charge | benefit
                'label' => $d->line_label,
                'qty'   => (int) $d->qty,
                'code'  => $d->line_type === 'product'
                    ? optional($d->product)->products_code
                    : ($d->line_type === 'charge' ? optional($d->otherCharge)->code : null),
                // Only product lines carry specifications; the card shows them
                // as a sub-list under the feature.
                'specifications' => $d->line_type === 'product' && $d->product
                    ? $d->product->specifications->map(fn ($spec) => [
                        'attribute' => $spec->attribute,
                        'value'     => $spec->value,
                    ])->values()
                    : [],
            ])->values(),
        ];
    }
}
