<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryProfileRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        return [
            'date_of_birth' => ['required','date','before:'.now()->subYears(18)->toDateString()],
            'national_id_number' => ['required','string','max:100'],
            'driving_license_number' => ['nullable','string','max:100'],
            'driving_license_expiry' => ['nullable','date','after:today'],
            'emergency_contact_name' => ['required','string','max:255'],
            'emergency_contact_phone' => ['required','string','max:50'],
            'vehicle_type' => ['nullable','string','max:50'],
            'vehicle_plate_number' => ['nullable','string','max:50'],
            'driving_license_document' => ['nullable','file','mimes:pdf,jpg,jpeg,png','max:5120'],
            'national_id_document' => ['required','file','mimes:pdf,jpg,jpeg,png','max:5120'],
            'vehicle_registration_document' => ['nullable','file','mimes:pdf,jpg,jpeg,png','max:5120'],
            'hourly_rate' => ['nullable','numeric','min:0'],
        ];
    }

    public function messages()
    {
        return [
            'date_of_birth.before' => 'You must be at least 18 years old to register as a delivery person.',
            'national_id_document.required' => 'National ID document is required.',
        ];
    }
}
