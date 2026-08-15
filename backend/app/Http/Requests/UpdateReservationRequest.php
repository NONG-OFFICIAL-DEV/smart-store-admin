<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateReservationRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // "sometimes" — this endpoint also serves the reservation list's
            // quick status actions (Confirm/Cancel/Seat/No-show/Complete),
            // which only send { status }. A field is validated when present,
            // but a partial update is never rejected for omitting the rest.
            'branch_id' => ['sometimes', 'required', 'uuid', 'exists:branches,id'],
            'table_id' => ['nullable', 'uuid', 'exists:tables,id'],
            'customer_id' => ['nullable', 'uuid', 'exists:customers,id'],
            'customer_name' => ['sometimes', 'required', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'party_size' => ['sometimes', 'required', 'integer', 'min:1'],
            // No after:now here — editing must still allow correcting or
            // reviewing a reservation that's already in the past (e.g.
            // completed/no_show/cancelled records), unlike creating a new one.
            'reserved_at' => ['sometimes', 'required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:pending,confirmed,seated,completed,no_show,cancelled'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
