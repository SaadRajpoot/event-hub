<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    /**
     * Send a notification.
     * Called by main-api to trigger email/SMS notifications.
     */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'      => ['required', 'string', 'max:100'],
            'channel'   => ['required', 'string', 'in:email,sms'],
            'recipient' => ['required', 'string', 'max:255'],
            'subject'   => ['nullable', 'string', 'max:255'],
            'payload'   => ['nullable', 'array'],
        ]);

        $log = $this->notificationService->send($data);

        return response()->json([
            'message' => 'Notification queued.',
            'data'    => ['id' => $log->id, 'status' => $log->status],
        ], 201);
    }

    /**
     * Send bulk notifications.
     */
    public function sendBulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'notifications'             => ['required', 'array', 'min:1', 'max:100'],
            'notifications.*.type'      => ['required', 'string', 'max:100'],
            'notifications.*.channel'   => ['required', 'string', 'in:email,sms'],
            'notifications.*.recipient' => ['required', 'string', 'max:255'],
            'notifications.*.subject'   => ['nullable', 'string', 'max:255'],
            'notifications.*.payload'   => ['nullable', 'array'],
        ]);

        $results = [];
        foreach ($data['notifications'] as $notification) {
            $log = $this->notificationService->send($notification);
            $results[] = ['id' => $log->id, 'status' => $log->status, 'recipient' => $log->recipient];
        }

        return response()->json([
            'message' => 'Bulk notifications processed.',
            'data'    => $results,
        ], 201);
    }

    /**
     * Get notification log (for debugging/admin).
     */
    public function index(Request $request): JsonResponse
    {
        $logs = NotificationLog::when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->recipient, fn($q) => $q->where('recipient', $request->recipient))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['data' => $logs]);
    }
}
