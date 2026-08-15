<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateProductRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Same FormData decoding as StoreProductRequest — see there for why.
     */
    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach (['variants', 'units', 'cup_sizes', 'temperature_options'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $decoded = json_decode($data[$field], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data[$field] = $decoded;
                }
            }
        }

        foreach (['is_available', 'is_featured', 'track_stock'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }

        if (isset($data['units']) && is_array($data['units'])) {
            foreach ($data['units'] as $index => $unit) {
                if (! is_array($unit)) {
                    continue;
                }
                foreach (['is_base_unit', 'is_active'] as $boolField) {
                    if (array_key_exists($boolField, $unit)) {
                        $data['units'][$index][$boolField] = filter_var($unit[$boolField], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    }
                }
            }
        }

        $this->merge($data);
    }

    /**
     * `sometimes` throughout — ProductManagement.vue's availability-toggle
     * sends only {is_available: bool} to this same endpoint, alongside
     * ProductFormPage.vue's full edit-form payload. A key that's present
     * still validates against the same rule as store(); a key that's
     * absent is simply not checked (and ProductService::update() only
     * touches the fields actually present in the payload).
     */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'required', 'uuid', 'exists:categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'sku' => ['nullable', 'string', 'max:60'],
            'barcode' => ['nullable', 'string', 'max:60'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_available' => ['sometimes', 'required', 'boolean'],
            'is_featured' => ['sometimes', 'required', 'boolean'],

            'base_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],

            'variants' => ['sometimes', 'nullable', 'array'],
            'variants.*.name' => ['required', 'string', 'max:100'],
            'variants.*.price_adjustment' => ['required', 'numeric', 'min:0'],
            'variants.*.is_default' => ['boolean'],
            'variants.*.sort_order' => ['integer', 'min:0'],

            'preparation_time' => ['nullable', 'integer', 'min:0'],
            'calories' => ['nullable', 'integer', 'min:0'],
            'cup_sizes' => ['nullable', 'array'],
            'cup_sizes.*' => ['string'],
            'temperature_options' => ['nullable', 'array'],
            'temperature_options.*' => ['string'],
            'shelf_life_hours' => ['nullable', 'integer', 'min:0'],

            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'track_stock' => ['sometimes', 'required', 'boolean'],
            'expiry_date' => ['nullable', 'date'],
            'supplier_code' => ['nullable', 'string', 'max:60'],

            'units' => ['sometimes', 'nullable', 'array'],
            'units.*.unit_name' => ['required', 'string', 'max:60'],
            'units.*.qty_per_base' => ['required', 'numeric', 'min:0.001'],
            'units.*.barcode' => ['nullable', 'string', 'max:60'],
            'units.*.retail_price' => ['required', 'numeric', 'min:0'],
            'units.*.wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'units.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'units.*.is_base_unit' => ['required', 'boolean'],
            'units.*.is_active' => ['required', 'boolean'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
