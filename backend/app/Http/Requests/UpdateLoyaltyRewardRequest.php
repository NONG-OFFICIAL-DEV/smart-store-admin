<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateLoyaltyRewardRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['uuid', 'exists:branches,id'],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'name_km' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'description_km' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'points_cost' => ['sometimes', 'required', 'integer', 'min:1'],
            'type' => ['nullable', 'in:discount_amount,discount_percent,free_item'],
            'discount_value' => ['required_if:type,discount_amount,discount_percent', 'nullable', 'numeric', 'min:0'],
            'free_product_id' => ['required_if:type,free_item', 'nullable', 'uuid', 'exists:products,id'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
