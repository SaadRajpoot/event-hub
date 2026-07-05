<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard(): JsonResponse
    {
        return response()->json(['data' => [
            'total_users'   => User::count(),
            'total_vendors' => Vendor::count(),
            'total_events'  => Event::count(),
            'total_orders'  => Order::count(),
            'confirmed_orders' => Order::where('status', 'confirmed')->count(),
            'pending_vendors'  => Vendor::where('status', 'pending')->count(),
        ]]);
    }

    public function listVendors(Request $request): JsonResponse
    {
        $vendors = Vendor::with('user')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['data' => $vendors]);
    }

    public function approveVendor(Vendor $vendor): JsonResponse
    {
        $vendor->update(['status' => 'active']);
        return response()->json(['message' => 'Vendor approved.', 'data' => $vendor]);
    }

    public function suspendVendor(Vendor $vendor): JsonResponse
    {
        $vendor->update(['status' => 'suspended']);
        return response()->json(['message' => 'Vendor suspended.', 'data' => $vendor]);
    }

    public function listEvents(Request $request): JsonResponse
    {
        $events = Event::with('vendor')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['data' => $events]);
    }

    public function featureEvent(Event $event): JsonResponse
    {
        $event->update(['is_featured' => !$event->is_featured]);
        return response()->json(['message' => 'Event feature status toggled.', 'data' => $event]);
    }

    public function getSettings(): JsonResponse
    {
        $settings = PlatformSetting::all()->keyBy('key');
        return response()->json(['data' => $settings]);
    }

    public function updateSetting(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key'   => ['required', 'string', 'max:100'],
            'value' => ['required'],
            'type'  => ['nullable', 'in:string,integer,decimal,boolean,json'],
        ]);

        PlatformSetting::set($data['key'], $data['value'], $data['type'] ?? 'string');

        return response()->json(['message' => 'Setting updated.']);
    }
}
