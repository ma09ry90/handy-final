<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterBuyerRequest;
use App\Http\Requests\Auth\RegisterSellerRequest;
use App\Http\Requests\Auth\RegisterDeliveryRequest;
use App\Models\ArtisanProfile;
use App\Models\User;
use App\Models\DeliveryProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validate Input
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // 2. Find User
        $user = User::where('email', $request->email)->first();

        // 3. Check Credentials & Status
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if ($user->account_status !== 'active') {
            return response()->json(['message' => "Your account is {$user->account_status}. Please contact support."], 403);
        }

        // 4. Update Last Login
        User::where('id', $user->id)->update(['last_login_at' => now()]);

        // 5. Create Token (REST API Auth)
        $token = $user->createToken('auth_token')->plainTextToken;

        // 6. Return JSON with Token
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('artisanProfile', 'deliveryProfile'), // Load relations if needed
        ]);
    }

    public function registerBuyer(RegisterBuyerRequest $request)
    {
        $data = $request->validated();
        
        $user = User::create([
            'name' => $data['first_name'] . ' ' . $data['last_name'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone_number' => $data['phone_number'],
            'birthdate' => $data['birthdate'],
            'role_id' => 1, // Buyer
            'account_status' => 'active',
        ]);

        // Auto-login buyer after registration (Issue Token)
        $token = $user->createToken('auth_token')->plainTextToken;
        
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], 201);
    }

    public function registerArtisan(RegisterSellerRequest $request)
    {
        $data = $request->validated();

        DB::beginTransaction();
        try {
            // 1. Create the User
            $user = User::create([
                'name' => $data['first_name'] . ' ' . $data['last_name'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone_number' => $data['phone_number'],
                'birthdate' => $data['birthdate'],
                'role_id' => 2, // Artisan
                'account_status' => 'pending', // Needs approval
            ]);

            // 2. Handle Document Uploads (Helper Method)
            $identityDocId = $this->uploadDocument($user->id, $request->file('identity_document'), 'identity_document');
            $businessDocId = $this->uploadDocument($user->id, $request->file('business_license_document'), 'business_license_document');
            $taxDocId = $this->uploadDocument($user->id, $request->file('tax_registration_document'), 'tax_registration_document');

            // 3. Handle Shop Logo (Optional)
            $logoPath = null;
            if ($request->hasFile('shop_logo')) {
                $logoPath = $request->file('shop_logo')->store('shop_logos', 'public');
            }

            // 4. Create Artisan Profile
            ArtisanProfile::create([
                'user_id' => $user->id,
                'shop_name' => $data['shop_name'],
                'shop_description' => $data['shop_description'] ?? null,
                'slang' => $data['slang'] ?? null,
                'shop_logo_path' => $logoPath,
                'business_license_number' => $data['business_license_number'],
                'tax_id' => $data['tax_id'],
                'bank_name' => $data['bank_name'],
                'bank_account_name' => $data['bank_account_name'],
                'bank_account_number' => $data['bank_account_number'],
                'identity_document_id' => $identityDocId,
                'business_license_document_id' => $businessDocId,
                'tax_registration_document_id' => $taxDocId,
                'approval_status' => 'pending',
                'submitted_at' => now(),
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json(['message' => 'Registration failed. Please try again.'], 500);
        }

        // Return Success JSON
        return response()->json(['message' => 'Registration submitted! Please wait for admin approval.'], 201);
    }

    protected function uploadDocument($userId, $file, $type)
    {
        $path = $file->store('documents', 'public');
        $doc = Document::create([
            'user_id' => $userId,
            'type' => $type,
            'path' => $path,
            'storage_disk' => 'public',
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_at' => now(),
        ]);
        return $doc->id;
    }

    public function registerDelivery(RegisterDeliveryRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['first_name'] . ' ' . $data['last_name'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone_number' => $data['phone_number'],
            'birthdate' => $data['birthdate'],
            'role_id' => 3, // Delivery
            'account_status' => 'pending',
        ]);

        DeliveryProfile::create([
            'user_id' => $user->id,
            'vehicle_type' => $data['vehicle_type'],
            'vehicle_plate_number' => $data['vehicle_plate_number'],
            'national_id_number' => $data['national_id_number'],
            'approval_status' => 'pending',
            'submitted_at' => now(),
        ]);

        return response()->json(['message' => 'Delivery registration submitted. Awaiting admin approval.'], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }
    
    public function user(Request $request) {
        return response()->json([
            'user' => $request->user()->load('artisanProfile', 'deliveryProfile')
        ]);
    }
}