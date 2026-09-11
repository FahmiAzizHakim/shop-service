<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OtherChargeRequest extends FormRequest
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
            'code' => [
                'required', 'string', 'max:60',
                // Unique per website, ignoring the current row on update.
                Rule::unique('other_charges', 'code')
                    ->where(fn ($q) => $q->where('website_id', $websiteId))
                    ->ignore($id),
            ],
            'name'        => 'required|string|max:191',
            'description' => 'nullable|string|max:5000',
            'remark'      => 'nullable|string|max:2000',
            'price'       => 'required|numeric|min:0',
            'is_active'   => 'required|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
