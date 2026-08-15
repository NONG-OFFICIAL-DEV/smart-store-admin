<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AdjustInventoryStockRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'uuid'],
            'ingredient_id' => ['required', 'uuid', 'exists:ingredients,id'],
            'quantity' => ['required', 'numeric'],
            'type' => ['required', 'in:purchase,usage,waste,adjustment,transfer_in,transfer_out,count'],
            'staff_id' => ['nullable', 'uuid', 'exists:staff,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
