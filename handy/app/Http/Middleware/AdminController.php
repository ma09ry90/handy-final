<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ArtisanProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AdminController extends Controller
{
    // Show Dashboard
    public function dashboard()
    {
        // Count stats
        $pendingArtisansCount = ArtisanProfile::where('approval_status', 'pending')->count();
        $totalBuyers = User::where('role_id', 1)->count();
        $totalArtisans = User::where('role_id', 2)->count();

        // Get recent pending artisans for the dashboard list
        $pendingArtisans = ArtisanProfile::with('user')
            ->where('approval_status', 'pending')
            ->latest()
            ->get();

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'pending_artisans' => $pendingArtisansCount,
                'total_buyers' => $totalBuyers,
                'total_artisans' => $totalArtisans,
            ],
            'pendingArtisans' => $pendingArtisans,
        ]);
    }

    // Show specific artisan details for approval
    public function showArtisan($id)
    {
        $profile = ArtisanProfile::with(['user', 'identityDocument', 'businessLicenseDocument', 'taxDocument'])
            ->findOrFail($id);

        return Inertia::render('admin/ArtisanDetail', [
            'profile' => $profile,
        ]);
    }

    // Approve Artisan
    public function approveArtisan($id)
    {
        $profile = ArtisanProfile::findOrFail($id);
        
        $profile->update([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        // Activate the user account so they can login
        $profile->user->update(['account_status' => 'active']);

        return redirect()->route('admin.dashboard')->with('status', 'Artisan approved successfully.');
    }

    // Reject Artisan
    public function rejectArtisan(Request $request, $id)
    {
        $request->validate(['rejection_reason' => 'required|string|max:1000']);

        $profile = ArtisanProfile::findOrFail($id);

        $profile->update([
            'approval_status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
        ]);

        return redirect()->route('admin.dashboard')->with('status', 'Artisan rejected.');
    }
}