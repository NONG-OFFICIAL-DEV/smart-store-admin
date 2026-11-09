<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateBranchRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * customer_display_settings is a nested object — FormData (used when a
     * promo/QR image file is attached) can't carry it any other way than a
     * JSON string, same pattern as UpdateProductRequest/
     * UpdateTenantProfileRequest's own nested-field decoding.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('customer_display_settings'))) {
            $decoded = json_decode($this->input('customer_display_settings'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['customer_display_settings' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'tax_rate' => ['nullable', 'numeric', 'min:0'],
            'service_charge_rate' => ['nullable', 'numeric', 'min:0'],
            'receipt_footer' => ['nullable', 'string'],
            'printer_name' => ['nullable', 'string', 'max:120'],
            'is_open' => ['boolean'],
            'is_active' => ['boolean'],
            'branch_type_id' => ['nullable', 'uuid', 'exists:branch_types,id'],

            // Food-category tenants only — gates whether checkout creates a
            // kitchen_display_tickets row at all (see CoffeePOSorderController/
            // OrderController). Off for branches that don't staff a Kitchen
            // Display, so orders stop piling up tickets no one progresses.
            'kitchen_ticket_enabled' => ['boolean'],
            'kitchen_auto_serve_hours' => ['nullable', 'integer', 'min:1', 'max:72'],

            // Second-screen customer-facing display — per branch (different
            // locations want different WiFi text/promos). Images are
            // uploaded as separate multipart files (see BranchController::
            // update()), promo_image_url/merchant_qr_image_url here are the
            // resulting URLs, not the uploads themselves.
            'customer_display_settings' => ['sometimes', 'array'],
            'customer_display_settings.enabled' => ['boolean'],
            'customer_display_settings.promo_image_url' => ['nullable', 'string', 'max:2048'],
            'customer_display_settings.promo_title' => ['nullable', 'string', 'max:120'],
            'customer_display_settings.promo_subtitle' => ['nullable', 'string', 'max:200'],
            'customer_display_settings.wifi_text' => ['nullable', 'string', 'max:120'],
            'customer_display_settings.thank_you_delay_seconds' => ['nullable', 'in:5,10,15'],
            'customer_display_settings.merchant_qr_image_url' => ['nullable', 'string', 'max:2048'],
            'customer_display_promo_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'customer_display_qr_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
