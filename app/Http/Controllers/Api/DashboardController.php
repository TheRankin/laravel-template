<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\TaskListResource;
use App\Models\ActivityLog;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        $statuses = ['todo', 'in_progress', 'in_review', 'done', 'cancelled'];
        $byStatus = array_fill_keys($statuses, 0);

        $statusCounts = Task::query()
            ->assignedTo($userId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        foreach ($statusCounts as $status => $total) {
            $byStatus[$status] = (int) $total;
        }

        $upcoming = Task::query()
            ->assignedTo($userId)
            ->with(['assignee', 'reporter'])
            ->withCount(['comments', 'attachments', 'subtasks as children_count'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', today())
            ->whereDate('due_date', '<=', today()->addDays(7))
            ->whereNotIn('status', ['done', 'cancelled'])
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        $overdueCount = Task::query()
            ->assignedTo($userId)
            ->overdue()
            ->count();

        $recent = ActivityLog::query()
            ->with('causer')
            ->where('causer_id', $userId)
            ->latest()
            ->limit(15)
            ->get();

        return response()->json([
            'my_tasks_by_status' => $byStatus,
            'upcoming' => TaskListResource::collection($upcoming),
            'overdue_count' => $overdueCount,
            'recent_activity' => ActivityLogResource::collection($recent),
        ]);
    }
}
