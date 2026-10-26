<?php

namespace App\Http\Requests;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateModifierGroupRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'name_km' => ['nullable', 'string', 'max:100'],
            'selection_type' => ['sometimes', 'required', 'in:single,multiple'],
            'max_selections' => ['nullable', 'integer', 'min:2', 'max:10'],
            'is_required' => ['boolean'],
            'allow_custom_value' => ['boolean'],
            'custom_suffix' => ['nullable', 'string', 'max:10'],

            'options' => ['sometimes', 'required', 'array', 'min:1'],
            'options.*.id' => ['nullable', 'uuid'],
            'options.*.name' => ['required', 'string', 'max:100'],
            'options.*.price_adjustment' => ['nullable', 'numeric'],
            'options.*.is_available' => ['boolean'],
            'options.*.is_default' => ['boolean'],
            'options.*.sort_order' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->has('options')) {
                return;
            }

            $options = collect($this->input('options', []));

            $names = $options->pluck('name')->filter()->map(fn ($n) => mb_strtolower(trim($n)));
            if ($names->count() !== $names->unique()->count()) {
                $validator->errors()->add('options', 'Choice names must be unique within the group.');
            }

            if ($this->input('selection_type') === 'single') {
                $defaults = $options->filter(fn ($o) => ! empty($o['is_default']))->count();
                if ($defaults > 1) {
                    $validator->errors()->add('options', 'Only one choice can be the default for "Pick one".');
                }
            }

            if ($this->input('selection_type') === 'multiple') {
                $max = (int) $this->input('max_selections', 0);
                if ($max > 0 && $max > $options->count()) {
                    $validator->errors()->add('max_selections', 'Max can\'t be more than the number of choices.');
                }
            }

            // is_required isn't always resent (the editor always does today,
            // but this request is a public API) — fall back to the current
            // group's own value so a partial update can't accidentally skip
            // this check.
            $isRequired = $this->has('is_required')
                ? $this->boolean('is_required')
                : (bool) $this->route('modifierGroup')?->is_required;

            if ($isRequired && $options->isNotEmpty()) {
                $hasAvailableChoice = $options->contains(fn ($o) => ($o['is_available'] ?? true) !== false);
                if (! $hasAvailableChoice) {
                    $validator->errors()->add(
                        'options',
                        "This group is required. Products using it can't be ordered until a choice is available."
                    );
                }
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
