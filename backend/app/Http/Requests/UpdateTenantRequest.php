<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateTenantRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'owner_first_name' => 'required|string|max:80',
            'owner_last_name' => 'required|string|max:80',
            'owner_phone' => 'nullable|string|max:30',
            'name' => 'required|string|max:150',
            'slug' => 'nullable|string|max:100|unique:tenants,slug,'.$this->route('tenant')?->id,
            'business_type_id' => 'required|uuid|exists:business_types,id',
            'logo_url' => 'nullable|url|max:500',
            'primary_color' => 'nullable|string|max:7',
            'is_active' => 'boolean',
            'currency' => 'nullable|string|size:3',
            'locale' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:60',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
