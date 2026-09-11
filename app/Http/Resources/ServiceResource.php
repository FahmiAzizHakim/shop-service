<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => (int) $this->id,
            'name'        => $this->service_name,
            'title'       => $this->service_title,
            'subtitle'    => $this->service_subtitle,
            'description' => $this->service_description,
            'icon'        => $this->service_icon,
            'image'       => $this->service_image ? asset($this->service_image) : null,
            // Only when the caller asked for the catalogue nested.
            'products'    => ProductResource::collection($this->whenLoaded('products')),
        ];
    }
}
