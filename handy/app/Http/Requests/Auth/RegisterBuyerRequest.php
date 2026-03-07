<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterBuyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone_number' => [
                'required',
                'string',
                'size:10',
                'regex:/^(09|07)\d{8}$/'
            ],
            'birthdate' => [
                'required',
                'date',
                'before_or_equal:' . now()->subYears(18)->format('Y-m-d')
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'phone_number.regex' => 'Phone number must start with 09 or 07 and be 10 digits.',
            'phone_number.size' => 'Phone number must be exactly 10 digits.',
            'birthdate.before_or_equal' => 'You must be at least 18 years old.',
        ];
    }
}