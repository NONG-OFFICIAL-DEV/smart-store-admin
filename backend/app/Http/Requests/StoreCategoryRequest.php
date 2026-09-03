<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreCategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
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

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
