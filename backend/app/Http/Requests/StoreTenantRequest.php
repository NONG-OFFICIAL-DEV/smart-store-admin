<?php

namespace App\Http\Requests;

use App\Rules\PasswordPolicy;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreTenantRequest extends FormRequest
{
    use ApiResponse;

    // Reachable both from the superadmin admin panel (POST /tenants) and
    // the public, unauthenticated self-service signup form (POST
    // /business-register) — must not gate on auth here.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Owner
            'owner_first_name' => 'required|string|max:80',
            'owner_last_name' => 'required|string|max:80',
            'owner_email' => 'required|email|unique:users,email',
            'owner_password' => PasswordPolicy::rules(),
            'owner_phone' => 'nullable|string|max:30',

            // Business
            'name' => 'required|string|max:150',
            'slug' => 'nullable|string|max:100|unique:tenants,slug',
            'business_type_id' => 'required|uuid|exists:business_types,id',
            'logo_url' => 'nullable|url|max:500',
            'primary_color' => 'nullable|string|max:7',

            // Localisation
            'timezone' => 'nullable|string|max:60',
            'currency' => 'nullable|string|size:3',
            'locale' => 'nullable|string|max:10',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
