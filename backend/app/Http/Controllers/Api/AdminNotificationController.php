<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;

class AdminNotificationController extends Controller
{
    public function index()
    {
        return response()->json(['data' => AdminNotification::latest()->limit(20)->get()]);
    }

    public function unreadCount()
    {
        return response()->json(['data' => ['count' => AdminNotification::whereNull('read_at')->count()]]);
    }

    public function read(AdminNotification $notification)
    {
        $notification->forceFill(['read_at' => now()])->save();

        return response()->json(['data' => $notification->fresh()]);
    }

    public function readAll()
    {
        AdminNotification::whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['data' => ['ok' => true]]);
    }
}
