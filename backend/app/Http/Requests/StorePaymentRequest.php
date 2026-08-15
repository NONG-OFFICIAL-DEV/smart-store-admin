<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorePaymentRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Existence + tenant ownership is checked in PaymentService::create
            // via Order::findOrFail(), which goes through TenantScope — a raw
            // exists:orders,id rule would bypass tenant scoping entirely.
            'order_id' => ['required', 'uuid'],
            'staff_id' => ['nullable', 'uuid', 'exists:staff,id'],
            'payment_method' => ['required', 'in:cash,card,qr_code,online,loyalty_points,voucher'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'change_given' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:pending,completed,failed'],
            'gateway' => ['nullable', 'string', 'max:60'],
            'gateway_transaction_id' => ['nullable', 'string', 'max:200'],
            'receipt_number' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
