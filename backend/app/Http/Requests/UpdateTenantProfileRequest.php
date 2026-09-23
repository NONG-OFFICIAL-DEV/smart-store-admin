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
 * identity. Those stay admin-only; this only ever touches branding fields
 * safe for an owner to change about their own tenant.
 */
class UpdateTenantProfileRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The Company Info form submits multipart/form-data whenever a new logo
     * file is attached (needed for the file upload itself) — but
     * `pos_settings` is a nested object, which FormData can't carry as a
     * real array, so the frontend JSON-encodes it into a single string
     * field in that case. Decode it back to an array here so `rules()`
     * validates the same shape either way (plain JSON PUT vs multipart).
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('pos_settings'))) {
            $decoded = json_decode($this->input('pos_settings'), true);
            if (is_array($decoded)) {
                $this->merge(['pos_settings' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'logo_url' => 'nullable|url|max:500',
            // A newly-picked file, if any — takes precedence over logo_url
            // in the controller (see updateProfile()). Same constraints as
            // the product image upload this UI reuses.
            'logo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'primary_color' => 'nullable|string|max:7',
            'currency' => 'nullable|string|size:3',
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
