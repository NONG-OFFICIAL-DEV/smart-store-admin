<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreBranchHourRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'day_of_week' => [
                'required', 'integer', 'between:0,6',
                Rule::unique('branch_hours', 'day_of_week')->where('branch_id', $this->route('branch')?->id),
            ],
            'open_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'close_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'is_closed' => ['boolean'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
