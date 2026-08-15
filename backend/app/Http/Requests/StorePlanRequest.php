<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorePlanRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'code' => ['required', 'string', 'max:30', 'unique:plans,code'],
            'price_usd' => ['required', 'numeric', 'min:0'],
            'price_khr' => ['nullable', 'numeric', 'min:0'],
            'seats' => ['required', 'integer', 'min:1'],
            'storage_gb' => ['required', 'integer', 'min:1'],
            'api_limit' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],

            'billing_cycles' => ['required', 'array', 'min:1'],
            'billing_cycles.*.label' => ['required', 'string', 'max:80'],
            'billing_cycles.*.months' => ['required', 'integer', 'in:1,3,6,12'],
            'billing_cycles.*.discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'billing_cycles.*.is_active' => ['boolean'],

            'features' => ['nullable', 'array'],
            'features.*.key' => ['required', 'string', 'max:80'],
            'features.*.en' => ['required', 'string', 'max:255'],
            'features.*.km' => ['nullable', 'string', 'max:255'],
            'features.*.sort_order' => ['nullable', 'integer'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
