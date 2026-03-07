<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateArtisanProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Basic Info
            'shop_name' => ['required', 'string', 'max:255'],
            'shop_description' => ['nullable', 'string'],
            'slang' => ['nullable', 'string', 'max:50'],
            'shop_address_id' => ['nullable', 'exists:addresses,id'], // Assuming addresses table exists
            
            // Business Details
            'business_license_number' => ['nullable', 'string', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            
            // Bank Details
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            
            // File Uploads (Documents)
            'shop_logo' => ['nullable', 'image', 'max:2048'], // 2MB Max
            'identity_document' => ['nullable', 'file', 'mimes:pdf,jpg,png', 'max:5120'],
            'business_license_document' => ['nullable', 'file', 'mimes:pdf,jpg,png', 'max:5120'],
            'tax_registration_document' => ['nullable', 'file', 'mimes:pdf,jpg,png', 'max:5120'],
            'additional_documents' => ['nullable', 'array'],
            'additional_documents.*' => ['file', 'max:5120'],
        ];
    }
}