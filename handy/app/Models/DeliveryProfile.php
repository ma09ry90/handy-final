<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','date_of_birth','national_id_number','driving_license_number','driving_license_expiry',
        'emergency_contact_name','emergency_contact_phone','vehicle_type','vehicle_plate_number','vehicle_model','vehicle_color',
        'driving_license_document_id','national_id_document_id','vehicle_registration_document_id','additional_documents',
        'assigned_zone','employment_type','hourly_rate','approval_status','approved_by','approved_at','rejection_reason',
        'background_check_status','background_check_date','background_check_notes','submitted_at'
    ];

    protected $dates = ['date_of_birth','driving_license_expiry','background_check_date','approved_at','submitted_at'];
    protected $casts = ['additional_documents' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
