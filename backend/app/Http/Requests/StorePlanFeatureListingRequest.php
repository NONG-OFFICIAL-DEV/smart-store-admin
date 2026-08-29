<?php

namespace App\Http\Requests;

use App\Enums\PlanFeatureValueType;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StorePlanFeatureListingRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', Rule::unique('plan_feature_listings', 'key')],
            'label_en' => ['required', 'string', 'max:255'],
            'label_km' => ['nullable', 'string', 'max:255'],
            'value_type' => ['required', Rule::enum(PlanFeatureValueType::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
