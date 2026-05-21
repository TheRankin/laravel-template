<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $query = $request->user()->notifications()->latest();

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        return NotificationResource::collection($query->paginate($perPage));
    }

    public function markRead(AppNotification $notification): NotificationResource
    {
        abort_if($notification->user_id !== request()->user()->id, 403, 'Forbidden.');

        $notification->forceFill(['read_at' => now()])->save();

        return new NotificationResource($notification);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $request->user()
            ->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['updated' => (int) $count]);
    }

    public function destroy(AppNotification $notification): Response
    {
        abort_if($notification->user_id !== request()->user()->id, 403, 'Forbidden.');

        $notification->delete();

        return response()->noContent();
    }
}
