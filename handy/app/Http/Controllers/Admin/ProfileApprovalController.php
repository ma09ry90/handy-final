<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArtisanProfile;
use App\Models\DeliveryProfile;
use App\Models\ProfileAuditLog;
use App\Notifications\ProfileStatusChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileApprovalController extends Controller
{
    public function approve(Request $request, $type, $id)
    {
        // Authorization Check
        if ((int) $request->user()->role_id !== (int) config('roles.admin', 4)) {
            return response()->json(['message' => 'Not authorized'], 403);
        }

        DB::beginTransaction();
        try {
            $adminId = $request->user()->id;

            if ($type === 'artisan') {
                $profile = ArtisanProfile::findOrFail($id);
            } else {
                $profile = DeliveryProfile::findOrFail($id);
            }

            $profile->approval_status = 'approved';
            $profile->approved_by = $adminId;
            $profile->approved_at = now();
            $profile->rejection_reason = null;
            $profile->save();

            ProfileAuditLog::create([
                'entity_type' => $type.'_profile',
                'entity_id' => $profile->id,
                'action' => 'approved',
                'actor_user_id' => $adminId,
                'data' => json_encode(['snapshot' => $profile->toArray()]),
            ]);

            $profile->user->notify(new ProfileStatusChanged($profile, 'approved'));

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            // Return JSON error instead of crashing
            return response()->json(['message' => 'An error occurred during approval.', 'error' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Profile approved successfully', 'profile' => $profile]);
    }

    public function reject(Request $request, $type, $id)
    {
        // Authorization Check
        if ((int) $request->user()->role_id !== (int) config('roles.admin', 4)) {
            return response()->json(['message' => 'Not authorized'], 403);
        }

        $request->validate(['rejection_reason' => 'required|string|max:1000']);

        DB::beginTransaction();
        try {
            $adminId = $request->user()->id;

            if ($type === 'artisan') {
                $profile = ArtisanProfile::findOrFail($id);
            } else {
                $profile = DeliveryProfile::findOrFail($id);
            }

            $profile->approval_status = 'rejected';
            $profile->approved_by = $adminId;
            $profile->approved_at = now();
            $profile->rejection_reason = $request->input('rejection_reason');
            $profile->save();

            ProfileAuditLog::create([
                'entity_type' => $type.'_profile',
                'entity_id' => $profile->id,
                'action' => 'rejected',
                'actor_user_id' => $adminId,
                'data' => json_encode(['reason' => $profile->rejection_reason, 'snapshot' => $profile->toArray()]),
            ]);

            $profile->user->notify(new ProfileStatusChanged($profile, 'rejected', $profile->rejection_reason));

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            // Return JSON error instead of crashing
            return response()->json(['message' => 'An error occurred during rejection.', 'error' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Profile rejected successfully', 'profile' => $profile]);
    }
}