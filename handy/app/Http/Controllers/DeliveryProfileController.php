<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryProfileRequest;
use App\Models\DeliveryProfile;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliveryProfileController extends Controller
{
    public function store(StoreDeliveryProfileRequest $request)
    {
        $user = $request->user();

        Log::info('DeliveryProfileController@store called', ['user_id' => $user?->id, 'role_id' => $user?->role_id, 'expected_delivery' => config('roles.delivery')]);

        if ((int) $user->role_id !== (int) config('roles.delivery', 3)) {
            Log::warning('DeliveryProfileController@store role mismatch', ['user_id' => $user?->id, 'role_id' => $user?->role_id, 'expected' => config('roles.delivery')]);
            abort(403, 'User role not permitted to create a delivery profile.');
        }

        DB::beginTransaction();
        try {
            $profile = DeliveryProfile::updateOrCreate(
                ['user_id' => $user->id],
                array_merge($request->only([
                    'date_of_birth','national_id_number','driving_license_number','driving_license_expiry',
                    'emergency_contact_name','emergency_contact_phone','vehicle_type','vehicle_plate_number',
                    'vehicle_model','vehicle_color','assigned_zone','employment_type','hourly_rate'
                ]), ['submitted_at' => now(), 'approval_status' => 'pending'])
            );

            if ($request->hasFile('national_id_document')) {
                $path = $request->file('national_id_document')->store('documents');
                $doc = Document::create([
                    'user_id' => $user->id,
                    'type' => 'national_id',
                    'path' => $path,
                    'mime_type' => $request->file('national_id_document')->getClientMimeType(),
                    'size' => $request->file('national_id_document')->getSize(),
                    'uploaded_at' => now(),
                ]);
                $profile->national_id_document_id = $doc->id;
                $profile->save();
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json(['message' => 'Delivery profile submitted for review', 'profile' => $profile], 201);
    }
}
