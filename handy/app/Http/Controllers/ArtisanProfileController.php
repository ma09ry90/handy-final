<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateArtisanProfileRequest;
use App\Models\ArtisanProfile;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ArtisanProfileController extends Controller
{
    public function edit(Request $request)
    {
        // Return JSON for the frontend to consume
        $profile = $request->user()->artisanProfile;
        
        return response()->json([
            'profile' => $profile,
        ]);
    }

    public function update(UpdateArtisanProfileRequest $request)
    {
        $user = $request->user();
        
        // Security Check
        if ((int) $user->role_id !== 2) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        DB::beginTransaction();
        try {
            $data = $request->validated();

            // 1. Update Text Fields
            $profile = ArtisanProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'shop_name' => $data['shop_name'],
                    'shop_description' => $data['shop_description'] ?? null,
                    'slang' => $data['slang'] ?? null,
                    'shop_address_id' => $data['shop_address_id'] ?? null,
                    'business_license_number' => $data['business_license_number'] ?? null,
                    'tax_id' => $data['tax_id'] ?? null,
                    'bank_account_name' => $data['bank_account_name'] ?? null,
                    'bank_name' => $data['bank_name'] ?? null,
                    'bank_account_number' => $data['bank_account_number'] ?? null,
                    // If profile was rejected, allow resubmission
                    'approval_status' => ($user->artisanProfile->approval_status === 'rejected') ? 'pending' : $user->artisanProfile->approval_status,
                ]
            );

            // 2. Handle Shop Logo
            if ($request->hasFile('shop_logo')) {
                if ($profile->shop_logo_path) {
                    Storage::disk('public')->delete($profile->shop_logo_path);
                }
                $profile->shop_logo_path = $request->file('shop_logo')->store('shop_logos', 'public');
                $profile->save();
            }

            // 3. Handle Document Uploads
            $this->storeDocument($request, $profile, 'identity_document', 'identity_document_id');
            $this->storeDocument($request, $profile, 'business_license_document', 'business_license_document_id');
            $this->storeDocument($request, $profile, 'tax_registration_document', 'tax_registration_document_id');

            // 4. Handle Additional Documents
            if ($request->hasFile('additional_documents')) {
                $paths = [];
                foreach ($request->file('additional_documents') as $file) {
                    $paths[] = $file->store('additional_docs', 'public');
                }
                $profile->additional_documents = $paths;
                $profile->save();
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json(['message' => 'Something went wrong.'], 500);
        }

        return response()->json(['message' => 'Profile updated successfully!', 'profile' => $profile]);
    }

    protected function storeDocument($request, $profile, $fileKey, $dbColumn)
    {
        if ($request->hasFile($fileKey)) {
            $file = $request->file($fileKey);
            $path = $file->store('documents', 'public');

            $doc = Document::create([
                'user_id' => $profile->user_id,
                'type' => $fileKey,
                'path' => $path,
                'storage_disk' => 'public',
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_at' => now(),
            ]);

            $profile->$dbColumn = $doc->id;
            $profile->save();
        }
    }
}