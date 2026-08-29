<?php

namespace App\Http\Requests;

use App\Enums\PlanFeatureValueType;
use App\Models\PlanFeatureListing;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdatePlanRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'code' => ['required', 'string', 'max:30', 'unique:plans,code,'.$this->route('plan')?->id],
            'price_usd' => ['required', 'numeric', 'min:0'],
            'price_khr' => ['nullable', 'numeric', 'min:0'],
            'seats' => ['required', 'integer', 'min:1'],
            'storage_gb' => ['required', 'integer', 'min:1'],
            'api_limit' => ['nullable', 'integer', 'min:0'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_active' => ['boolean'],

            'billing_cycles' => ['required', 'array', 'min:1'],
            'billing_cycles.*.id' => ['nullable', 'uuid', 'exists:plan_billing_cycles,id'],
            'billing_cycles.*.label' => ['required', 'string', 'max:80'],
            'billing_cycles.*.months' => ['required', 'integer', 'in:1,3,6,12'],
            'billing_cycles.*.discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'billing_cycles.*.is_active' => ['boolean'],

            'features' => ['nullable', 'array'],
            'features.*.id' => ['nullable', 'uuid', 'exists:plan_features,id'],
            'features.*.key' => ['required', 'string', 'max:80'],
            'features.*.value' => ['required'],
            'features.*.sort_order' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Not filtered to is_active — a plan can legitimately keep a
            // value for a key that was later deactivated (not deleted) in
            // the catalog. Only a nonexistent (or soft-deleted) key is
            // "unknown" here.
            $catalog = PlanFeatureListing::query()->get()->keyBy('key');

            foreach ($this->input('features', []) as $i => $feature) {
                $key = $feature['key'] ?? null;
                $listing = $key ? $catalog->get($key) : null;

                if (! $listing) {
                    $validator->errors()->add("features.{$i}.key", "Unknown feature key \"{$key}\".");

                    continue;
                }

                $value = $feature['value'] ?? null;

                if ($listing->value_type === PlanFeatureValueType::Boolean) {
                    if (! is_bool($value)) {
                        $validator->errors()->add("features.{$i}.value", "The \"{$key}\" feature must be true or false.");
                    }
                } elseif (! is_array($value) || empty($value['en'])) {
                    $validator->errors()->add("features.{$i}.value.en", "The \"{$key}\" feature's English value is required.");
                }
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
