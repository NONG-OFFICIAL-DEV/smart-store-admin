<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateTableRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['sometimes', 'required', 'uuid', 'exists:branches,id'],
            'floor_plan_id' => ['nullable', 'uuid', 'exists:floor_plans,id'],
            'table_number' => [
                'sometimes', 'required', 'string', 'max:20',
                Rule::unique('tables', 'table_number')
                    ->where('branch_id', $this->input('branch_id') ?? $this->route('table')?->branch_id)
                    ->ignore($this->route('table')),
            ],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'shape' => ['nullable', 'in:round,square,rectangle,bar'],
            'position_x' => ['nullable', 'integer'],
            'position_y' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:available,occupied,reserved,cleaning,inactive'],
            'is_active' => ['boolean'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
