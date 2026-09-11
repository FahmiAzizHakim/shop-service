<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level: the gateway's JWT middleware gates these endpoints.
        return true;
    }

    public function rules(): array
    {
        $wid = admin_website_id();

        return [
            // The service must belong to the current admin's website.
            'service_id' => [
                'required',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('website_id', $wid)),
            ],
            'products_name'        => 'required|string|max:191',
            'products_code'        => 'required|string|max:100',
            'products_price'       => 'nullable|numeric|min:0',
            'products_description' => 'nullable|string|max:5000',
            'products_weight'      => 'nullable|integer|min:0',
            'products_width'       => 'nullable|integer|min:0',
            'products_length'      => 'nullable|integer|min:0',
            'products_height'      => 'nullable|integer|min:0',
            'remark'               => 'nullable|string|max:2000',
            'is_active'            => 'required|boolean',

            // Many-to-many categories (must belong to this website).
            'categories'   => 'nullable|array',
            'categories.*' => [Rule::exists('categories', 'id')->where(fn ($q) => $q->where('website_id', $wid))],

            // Multiple new images.
            'images'   => 'nullable|array',
            'images.*' => 'file|mimes:jpg,jpeg,png,gif,webp|max:4096',

            // Ids of existing images to remove.
            'remove_images'   => 'nullable|array',
            'remove_images.*' => 'integer',

            // Variants (repeatable rows).
            'variants'                        => 'nullable|array',
            'variants.*.id'                   => 'nullable|integer',
            'variants.*.variant_name'         => 'nullable|string|max:191',
            'variants.*.variant_price'        => 'nullable|numeric|min:0',
            'variants.*.variant_description'  => 'nullable|string|max:1000',
            'variants.*.variant_weight'       => 'nullable|integer|min:0',
            'variants.*.variant_width'        => 'nullable|integer|min:0',
            'variants.*.variant_length'       => 'nullable|integer|min:0',
            'variants.*.variant_height'       => 'nullable|integer|min:0',

            // Specifications (repeatable attribute / value rows).
            'specifications'             => 'nullable|array',
            'specifications.*.id'        => 'nullable|integer',
            'specifications.*.attribute' => 'nullable|string|max:100',
            'specifications.*.value'     => 'nullable|string|max:300',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
