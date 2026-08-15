<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreBranchMenuRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Accept either a single branch id or an array of ids — normalize
        // to an array so validation and the Service both handle one shape.
        if ($this->has('branch_id') && ! is_array($this->branch_id)) {
            $this->merge(['branch_id' => [$this->branch_id]]);
        }
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'array', 'min:1'],
            'branch_id.*' => ['uuid', 'exists:branches,id'],
            'menu_id' => ['required', 'uuid', 'exists:menus,id'],
            'available_from' => ['nullable', 'date_format:H:i'],
            'available_until' => ['nullable', 'date_format:H:i', 'after:available_from'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'min:0', 'max:6'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
