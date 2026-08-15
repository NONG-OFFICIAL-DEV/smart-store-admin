<?php

namespace App\Http\Requests;

use App\Models\Staff;
use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class TransferTenantOwnershipRequest extends FormRequest
{
    use ApiResponse;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenant = $this->route('tenant');

        return [
            'new_owner_user_id' => [
                'required',
                'uuid',
                function ($attribute, $value, $fail) use ($tenant) {
                    $isExistingStaff = Staff::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('user_id', $value)
                        ->exists();
                    if (! $isExistingStaff) {
                        $fail('The new owner must already be an existing staff member of this business.');
                    }
                },
            ],
            'demote_role_id' => 'nullable|uuid|required_with:demote_branch_id',
            'demote_branch_id' => 'nullable|uuid|required_with:demote_role_id',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->error('Validation failed.', 422, $validator->errors()->toArray(), 'VALIDATION_FAILED'));
    }
}
