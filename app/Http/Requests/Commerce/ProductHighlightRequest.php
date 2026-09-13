<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductHighlightRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level: the gateway's JWT middleware gates these endpoints.
        return true;
    }

    public function rules(): array
    {
        $websiteId = admin_website_id();
        $id        = $this->route('id');

        return [
            'highlight_name' => [
                'required', 'string', 'max:191',
                // Unique per website, ignoring the current row on update: the
                // name is the heading the storefront prints, and two identical
                // headings are a mistake rather than a choice.
                Rule::unique('product_highlights', 'highlight_name')
                    ->where(fn ($q) => $q->where('website_id', $websiteId))
                    ->ignore($id),
            ],

            'is_active' => 'required|boolean',

            /*
             * The set of products, whole. Flat ids rather than the `details`
             * rows a package posts, because a highlight line carries nothing
             * but the product -- see ProductHighlightService::syncProducts().
             *
             * Allowed to be empty: a highlight is normally named and saved
             * before anyone picks what goes in it, and emptying one is how an
             * editor clears a set without losing the heading.
             */
            'product_ids'   => 'nullable|array',
            'product_ids.*' => [
                'required', 'integer',
                // Products belong to a website through their service, so the
                // check has to follow that hop -- the same subquery
                // PackageRequest uses for a detail line's product.
                Rule::exists('products', 'id')->where(function ($q) use ($websiteId) {
                    $q->whereIn('service_id', function ($sub) use ($websiteId) {
                        $sub->select('id')->from('services')->where('website_id', $websiteId);
                    });
                }),
            ],
        ];
    }

    /**
     * Readable names so line errors don't surface as "product_ids.0".
     */
    public function attributes(): array
    {
        return [
            'highlight_name' => 'name',
            'product_ids'    => 'products',
            'product_ids.*'  => 'product',
        ];
    }

    public function messages(): array
    {
        return [
            'product_ids.*.exists' => 'One of the selected products is not on this website.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
