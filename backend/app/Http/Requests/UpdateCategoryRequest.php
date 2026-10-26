<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateCategoryRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'name_km' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
            'show_on_pos' => ['boolean'],
            'show_on_emenu' => ['boolean'],
            'is_lid_exchange' => ['boolean'],
            // is_system / business_type_ids are only ever honored from a
            // super-admin caller — CategoryService enforces that, this just
            // validates shape.
            'is_system' => ['boolean'],
            'tenant_ids' => ['nullable', 'array'],
            'tenant_ids.*' => ['uuid', 'exists:tenants,id'],
            'business_type_ids' => ['nullable', 'array'],
            'business_type_ids.*' => ['uuid', 'exists:business_types,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $name = trim((string) $this->input('name'));
            if ($name === '') {
                return;
            }

            $user = auth()->user();
            $tenantId = $user?->ownedTenant?->id
                ?? $user?->staff()->withoutGlobalScopes()->first()?->tenant_id;
            $categoryId = $this->route('category')?->id ?? $this->route('category');

            $exists = Category::whereHas('tenants', fn ($q) => $q->where('tenant_id', $tenantId))
                ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
                ->where('id', '!=', $categoryId)
                ->exists();

            if ($exists) {
                $validator->errors()->add('name', 'A category with this name already exists.');
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
