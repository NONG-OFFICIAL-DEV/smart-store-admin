<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($this->route('customer'))],
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar_url' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'marketing_opt_in' => ['nullable', 'boolean'],
            'source' => ['nullable', 'in:walk_in,online,referral,import'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
