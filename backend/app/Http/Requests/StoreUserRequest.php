<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class StoreUserRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Allow all
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'username'  => 'required|unique:users,username',
            'password'  => 'required|min:6',
            'role_id'   => 'required|exists:roles,id',
            'status'    => 'required',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Full name is required.',
            'email.required'     => 'Email is required.',
            'email.email'        => 'Email must be a valid email address.',
            'email.unique'       => 'This email is already taken.',
            'username.required'  => 'Username is required.',
            'username.unique'    => 'This username is already taken.',
            'password.required'  => 'Password is required.',
            'password.min'       => 'Password must be at least 6 characters.',
            'role_id.required'   => 'Role is required.',
            'role_id.exists'     => 'Selected role does not exist.',
            'status.required'    => 'Status is required.',
            'status.in'          => 'Status must be either Active or Inactive.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new ValidationException(
            $validator,
            response()->json([
                'message' => 'Validation Failed',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
