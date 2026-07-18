<?php

namespace App\Http\Requests;

use Auth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AuthRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (
            $this->route()->named('api_login') 
            || $this->route()->named('api_sign-up')
            || $this->route()->named('admin.login.submit')
        ) {
            return true;
        }
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->route() && $this->route()->named('api_login')) {
            return [
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string',
            ];
        }

        if ($this->route() && $this->route()->named('api_sign-up')) {
            return [
                'name' => 'required|string|max:255',
                'email' => 'required|email',
                'phone' => 'sometimes|nullable|string|max:20',
                'gender' => 'sometimes|nullable|string|max:20',
                'date_of_birth' => 'sometimes|nullable|date',
                'password' => [
                    'required',
                    'confirmed',
                    Password::min(8)
                        ->letters()
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                ],
            ];
        }

        return [
            'name' => 'sometimes|nullable|string|max:255',
            'email' => 'required|email',
            'password' => 'required|string',
        ];
    }
}
