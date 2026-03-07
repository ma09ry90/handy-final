<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterSellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // User Info
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', 'min:8'],
            'phone_number' => ['required', 'string', 'max:20'],
            'birthdate' => ['required', 'date'],

            // Shop Info (Essential)
            'shop_name' => ['required', 'string', 'max:255'],
            
            // Business Info (Essential for approval)
            'business_license_number' => ['required', 'string', 'max:100'],
            'tax_id' => ['required', 'string', 'max:100'],
            
            // Bank Info (Essential for payouts)
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_account_name' => ['required', 'string', 'max:255'],
            'bank_account_number' => ['required', 'string', 'max:50'],

            // Required Documents
            'identity_document' => ['required', 'file', 'mimes:pdf,jpg,png,jpeg', 'max:5120'], // 5MB
            'business_license_document' => ['required', 'file', 'mimes:pdf,jpg,png,jpeg', 'max:5120'],
            'tax_registration_document' => ['required', 'file', 'mimes:pdf,jpg,png,jpeg', 'max:5120'],
            
            // Optional Files
            'shop_logo' => ['nullable', 'image', 'max:2048'],
            
            // Non-essential text
            'shop_description' => ['nullable', 'string'],
            'slang' => ['nullable', 'string', 'max:50'],
        ];
    }
    public function messages(): array
    {
        return [
            'phone_number.regex' => 'Phone number must start with 09 or 07 and be 10 digits.',
            'birthdate.before_or_equal' => 'You must be at least 18 years old to register as a seller.',
        ];
    }
}