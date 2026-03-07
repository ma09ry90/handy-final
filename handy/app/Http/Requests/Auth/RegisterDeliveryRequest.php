<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeliveryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // User Fields
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone_number' => ['required', 'size:10', 'regex:/^(09|07)\d{8}$/'],
            'birthdate' => ['required', 'date', 'before_or_equal:' . now()->subYears(18)->format('Y-m-d')],

            // Delivery Fields
            'vehicle_type' => 'required|string',
            'vehicle_plate_number' => 'required|string',
            'national_id_number' => 'required|string',
            // Add other delivery fields as needed
        ];
    }
    
    public function messages(): array
    {
        return [
            'birthdate.before_or_equal' => 'You must be at least 18 years old.',
        ];
    }
}