<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;

class BankRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level: the gateway's JWT middleware gates these endpoints.
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_name'    => 'required|string|max:191',
            'bank_account' => 'required|string|max:60',
            'account_name' => 'required|string|max:191',
            'branch'       => 'nullable|string|max:191',
            'logo'         => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,svg|max:2048',
            'remark'       => 'nullable|string|max:2000',
            'is_active'    => 'required|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
