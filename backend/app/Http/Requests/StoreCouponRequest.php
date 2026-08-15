<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreCouponRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Existence + tenant ownership is checked in CouponService::create
            // via Promotion::findOrFail(), which goes through TenantScope —
            // a raw `exists:promotions,id` rule here would run outside
            // Eloquent and bypass tenant scoping entirely, letting a
            // cross-tenant promotion_id pass validation.
            'promotion_id' => ['required', 'uuid'],
            'code' => ['nullable', 'string', 'max:40', 'unique:coupons,code'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
