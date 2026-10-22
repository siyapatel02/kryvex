<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        $notifications = DB::connection('mongodb')
            ->table('user_notifications')
            ->where('user_uid', $user['uid'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $unreadCount = DB::connection('mongodb')
            ->table('user_notifications')
            ->where('user_uid', $user['uid'])
            ->where('read_at', null)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    public function markRead(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        DB::connection('mongodb')
            ->table('user_notifications')
            ->where('user_uid', $user['uid'])
            ->where('read_at', null)
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Notifications marked as read.',
        ]);
    }
}
