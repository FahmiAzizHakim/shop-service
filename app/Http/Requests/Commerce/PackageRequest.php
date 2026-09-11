<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PackageRequest extends FormRequest
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
            // Optional: a package need not be tied to one service, but when it
            // is, the service has to belong to this admin's website.
            'service_id' => [
                'nullable',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('website_id', $websiteId)),
            ],
            'package_name' => 'required|string|max:191',
            'package_code' => [
                'required', 'string', 'max:100',
                // Unique per website, ignoring the current row on update.
                Rule::unique('packages', 'package_code')
                    ->where(fn ($q) => $q->where('website_id', $websiteId))
                    ->ignore($id),
            ],
            'package_price'       => 'nullable|numeric|min:0',
            'package_discount'    => 'nullable|numeric|min:0|lte:package_price',
            'package_description' => 'nullable|string|max:5000',
            'remark'              => 'nullable|string|max:2000',
            'is_active'           => 'required|boolean',

            // Detail lines (repeatable). Each line is one of product / charge / benefit.
            'details'                   => 'nullable|array',
            'details.*.id'              => 'nullable|integer',
            'details.*.line_type'       => 'nullable|in:product,charge,benefit',
            'details.*.product_id'      => [
                'nullable',
                // Products belong to a website through their service.
                Rule::exists('products', 'id')->where(function ($q) use ($websiteId) {
                    $q->whereIn('service_id', function ($sub) use ($websiteId) {
                        $sub->select('id')->from('services')->where('website_id', $websiteId);
                    });
                }),
            ],
            'details.*.other_charge_id' => [
                'nullable',
                Rule::exists('other_charges', 'id')->where(fn ($q) => $q->where('website_id', $websiteId)),
            ],
            'details.*.other_benefit'   => 'nullable|string|max:200',
            'details.*.qty'             => 'nullable|integer|min:1|max:65535',
        ];
    }

    /**
     * Readable names so line errors don't surface as "details.0.product_id".
     */
    public function attributes(): array
    {
        return [
            'service_id'               => 'service',
            'details.*.line_type'       => 'line type',
            'details.*.product_id'      => 'product',
            'details.*.other_charge_id' => 'other charge',
            'details.*.other_benefit'   => 'benefit',
            'details.*.qty'             => 'quantity',
        ];
    }

    public function messages(): array
    {
        return [
            'package_discount.lte' => 'The discount cannot be greater than the package price.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            // An empty select must land as null, not as ''.
            'service_id'       => $this->input('service_id') ?: null,
            'is_active'        => $this->boolean('is_active'),
            'package_price'    => $this->input('package_price') ?: 0,
            'package_discount' => $this->input('package_discount') ?: 0,
        ]);
    }
}
