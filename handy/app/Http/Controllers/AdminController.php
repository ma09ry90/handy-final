<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ArtisanProfile;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Display the admin dashboard data.
     */
    public function dashboard()
    {
        // 1. Stats
        $pendingArtisansCount = ArtisanProfile::where('approval_status', 'pending')->count();
        $totalBuyers = User::where('role_id', 1)->count();
        $totalArtisans = User::where('role_id', 2)->count();

        // 2. Get pending artisans list
        $pendingArtisans = ArtisanProfile::with('user')
            ->where('approval_status', 'pending')
            ->latest('submitted_at')
            ->get();

        // Return JSON Data
        return response()->json([
            'stats' => [
                'pending_artisans' => $pendingArtisansCount,
                'total_buyers' => $totalBuyers,
                'total_artisans' => $totalArtisans,
            ],
            'pendingArtisans' => $pendingArtisans,
        ]);
    }
}