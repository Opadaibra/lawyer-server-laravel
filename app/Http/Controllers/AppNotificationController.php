<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AppNotification;

class AppNotificationController extends Controller
{
    public function index()
    {
        $notifications = AppNotification::where('user_id', auth()->id())
            ->latest()
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $notifications
        ]);
    }

    public function clientNotifications()
    {
        $user = auth()->user();
        $clientProfile = $user->clientProfile;

        if (!$clientProfile) {
            return response()->json([
                'status' => 'error',
                'message' => 'User is not a client'
            ], 403);
        }

        $caseIds = $clientProfile->cases()->pluck('id');
        $taskIds = \App\Models\Task::whereIn('case_file_id', $caseIds)->pluck('id');
        $minuteIds = \App\Models\Minute::whereIn('case_file_id', $caseIds)->pluck('id');

        $notifications = AppNotification::where(function($query) use ($user, $caseIds, $taskIds, $minuteIds) {
            $query->where('user_id', $user->id)
                  ->orWhereIn('case_file_id', $caseIds)
                  ->orWhereIn('task_id', $taskIds)
                  ->orWhereIn('minute_id', $minuteIds);
        })
        ->with(['task:id,title', 'minute:id,title', 'caseFile:id,case_number'])
        ->latest()
        ->get();

        return response()->json([
            'status' => 'success',
            'data' => $notifications
        ]);
    }

    public function clientMarkAsRead($id)
    {
        $user = auth()->user();
        $clientProfile = $user->clientProfile;

        if (!$clientProfile) {
            return response()->json([
                'status' => 'error',
                'message' => 'User is not a client'
            ], 403);
        }

        $caseIds = $clientProfile->cases()->pluck('id');
        $taskIds = \App\Models\Task::whereIn('case_file_id', $caseIds)->pluck('id');
        $minuteIds = \App\Models\Minute::whereIn('case_file_id', $caseIds)->pluck('id');

        $notification = AppNotification::where(function($query) use ($user, $caseIds, $taskIds, $minuteIds) {
            $query->where('user_id', $user->id)
                  ->orWhereIn('case_file_id', $caseIds)
                  ->orWhereIn('task_id', $taskIds)
                  ->orWhereIn('minute_id', $minuteIds);
        })->findOrFail($id);

        $notification->update(['is_read' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Notification marked as read',
            'data' => $notification
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'title' => 'nullable|string|max:255',
            'message' => 'required|string',
            'task_id' => 'nullable|exists:tasks,id',
            'minute_id' => 'nullable|exists:minutes,id',
            'case_file_id' => 'nullable|exists:case_files,id',
        ]);

        $notification = AppNotification::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Notification created successfully',
            'data' => $notification
        ], 201);
    }

    public function markAsRead($id)
    {
        $notification = AppNotification::where('user_id', auth()->id())->findOrFail($id);
        $notification->update(['is_read' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Notification marked as read',
            'data' => $notification
        ]);
    }
}
