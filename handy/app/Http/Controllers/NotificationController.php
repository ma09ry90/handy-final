<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Return the current user's notifications for the dashboard.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $unread = $user->unreadNotifications()->orderBy('created_at', 'desc')->get();
        $read = $user->readNotifications()->orderBy('created_at', 'desc')->limit(50)->get();

        return response()->json([
            'unread_count' => $unread->count(),
            'unread' => $unread->values(),
            'read' => $read->values(),
        ]);
    }
}
