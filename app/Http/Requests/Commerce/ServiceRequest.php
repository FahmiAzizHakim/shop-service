<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level: the gateway's JWT middleware gates these endpoints.
        return true;
    }

    public function rules(): array
    {
        return [
            'service_name'        => 'required|string|max:191',
            'service_title'       => 'nullable|string|max:191',
            'service_subtitle'    => 'nullable|string|max:191',
            'service_description' => 'nullable|string|max:5000',
            'service_image'       => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,svg|max:4096',
            // A Bootstrap Icons class name ("bi-truck"), not a file: the
            // storefront renders it as <i class="bi ...">, so a path here would
            // land in a class attribute and show nothing. Constrained to the
            // characters a class name can hold, since that is where it ends up.
            'service_icon'        => ['nullable', 'string', 'max:191', 'regex:/^[A-Za-z0-9_\- ]+$/'],
            'remark'              => 'nullable|string|max:2000',
            'is_active'           => 'required|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
