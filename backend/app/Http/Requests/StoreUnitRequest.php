<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreUnitRequest extends FormRequest
{
    // Authorize the request
    public function authorize(): bool
    {
        return true;
    }

    // Validation rules
    public function rules(): array
    {
        return [
            'name' => 'required|string|unique:units,name',
            'abbreviation' => 'required|string|unique:units,abbreviation',
            'description' => 'string',
        ];
    }

    // Custom failed validation response (JSON)
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'The name has already been taken.',
            'errors' => $validator->errors()
        ], 200));
    }
}
