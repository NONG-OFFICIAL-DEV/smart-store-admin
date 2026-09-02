<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Self-service company-info update (tenant owner) — deliberately a much
 * narrower field set than UpdateTenantRequest (the super-admin edit form),
 * which also lets an admin change business_type_id/is_active/slug/owner
 * identity. Those stay admin-only; this only ever touches branding/locale
 * fields safe for an owner to change about their own tenant.
 */
class UpdateTenantProfileRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'logo_url' => 'nullable|url|max:500',
            'primary_color' => 'nullable|string|max:7',
            'currency' => 'nullable|string|size:3',
            'locale' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:60',
            // Which POS controls actually show on the POS screen — optional
            // subset of the 3 order types, plus two independent toggles.
            'pos_settings' => 'nullable|array',
            'pos_settings.order_types' => 'array',
            'pos_settings.order_types.*' => 'in:dine_in,takeaway,delivery',
            'pos_settings.customer_selection' => 'boolean',
            'pos_settings.order_notes' => 'boolean',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
