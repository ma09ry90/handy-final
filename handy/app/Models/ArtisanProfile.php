<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArtisanProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','shop_name','shop_address_id','shop_description','shop_logo_path','slang',
        'business_license_number','tax_id','bank_account_name','bank_name','bank_account_number',
        'business_license_document_id','tax_registration_document_id','identity_document_id','additional_documents',
        'approval_status','approved_by','approved_at','rejection_reason','submitted_at'
    ];

    protected $casts = ['additional_documents' => 'array','approved_at' => 'datetime','submitted_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function businessLicenseDocument()
    {
        return $this->belongsTo(Document::class, 'business_license_document_id');
    }

    public function identityDocument()
    {
        return $this->belongsTo(Document::class, 'identity_document_id');
    }
    // ADD THIS MISSING RELATIONSHIP
    public function taxRegistrationDocument()
    {
        return $this->belongsTo(Document::class, 'tax_registration_document_id');
    }
}
