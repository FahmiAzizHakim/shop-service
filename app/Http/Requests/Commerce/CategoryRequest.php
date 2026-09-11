<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level: the gateway's JWT middleware gates these endpoints.
        return true;
    }

    public function rules(): array
    {
        $id  = $this->route('id');
        $wid = admin_website_id();

        return [
            'category_name' => 'required|string|max:191',
            'category_code' => [
                'required', 'string', 'max:100',
                Rule::unique('categories', 'category_code')
                    ->where(fn ($q) => $q->where('website_id', $wid))
                    ->ignore($id),
            ],
            /*
             * The parent, if it has one -- and not one that would make a loop.
             *
             * `different:__self` used to stand in for the first check and did
             * not work: the route id is a string and parent_id arrives as an
             * integer, so the strict comparison behind `different` never
             * matched and a category could be saved as its own parent.
             *
             * The closure covers the rest of the family. Walking up from the
             * proposed parent, meeting this row means the move would put a
             * category underneath its own descendant -- which is a cycle, and
             * would hang anything that renders the tree.
             */
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(fn ($q) => $q->where('website_id', $wid)),
                Rule::notIn(array_filter([$id])),
                function ($attribute, $value, $fail) use ($id, $wid) {
                    if (!$id || !$value) {
                        return;
                    }

                    $seen   = [];
                    $cursor = $value;

                    while ($cursor && !in_array($cursor, $seen, false)) {
                        if ((int) $cursor === (int) $id) {
                            $fail('A category cannot be moved under one of its own sub-categories.');

                            return;
                        }

                        $seen[] = $cursor;
                        $cursor = \App\Models\Category::where('website_id', $wid)
                            ->where('id', $cursor)
                            ->value('parent_id');
                    }
                },
            ],
            'remark'    => 'nullable|string|max:2000',
            'is_active' => 'required|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function messages(): array
    {
        return [
            'parent_id.different' => 'A category cannot be its own parent.',
        ];
    }
}
