<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level: the gateway's JWT middleware gates these endpoints.
        return true;
    }

    public function rules(): array
    {
        return [
            // Province is only a UI filter for the city list; it is not stored.
            'province_code' => ['nullable', 'string', Rule::exists('glb_provinces', 'province_code')],

            // City is the minimum (required) level.
            'city_code' => ['required', 'string', Rule::exists('glb_cities', 'city_code')],

            // District optional, but must belong to the chosen city.
            'district_code' => [
                'nullable', 'string',
                Rule::exists('glb_districts', 'district_code')
                    ->where(fn ($q) => $q->where('city_code', $this->input('city_code'))),
            ],

            // Subdistrict optional, but must belong to the chosen district
            // (the "district required" dependency is enforced in withValidator()).
            'subdistrict_code' => [
                'nullable', 'string',
                Rule::exists('glb_subdistricts', 'subdistrict_code')
                    ->where(fn ($q) => $q->where('district_code', $this->input('district_code'))),
            ],

            'price'     => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // A subdistrict cannot be set without a district.
            if ($this->filled('subdistrict_code') && !$this->filled('district_code')) {
                $v->errors()->add('district_code', 'Select a district before choosing a subdistrict.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active'        => $this->boolean('is_active'),
            'district_code'    => $this->input('district_code') ?: null,
            'subdistrict_code' => $this->input('subdistrict_code') ?: null,
        ]);
    }
}
