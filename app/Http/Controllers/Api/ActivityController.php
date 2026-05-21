<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $teamIds = $user->teams()->pluck('teams.id')->all();
        $projectIds = $user->projectMemberships()->pluck('projects.id')->all();

        $query = ActivityLog::query()->with('causer')->latest();

        if (! ($user->role === 'admin')) {
            $query->where(function ($outer) use ($teamIds, $projectIds) {
                $outer->where(function ($null) {
                    $null->whereNull('team_id')->whereNull('project_id');
                });

                if (! empty($teamIds)) {
                    $outer->orWhereIn('team_id', $teamIds);
                }

                if (! empty($projectIds)) {
                    $outer->orWhereIn('project_id', $projectIds);
                }
            });
        }

        if ($request->filled('team_id')) {
            $query->where('team_id', (int) $request->input('team_id'));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', (int) $request->input('project_id'));
        }

        if ($request->filled('causer_id')) {
            $query->where('causer_id', (int) $request->input('causer_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', (string) $request->input('action'));
        }

        return ActivityLogResource::collection($query->paginate($perPage));
    }

    public function forProject(Project $project, Request $request)
    {
        $this->authorize('view', $project);

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $logs = ActivityLog::query()
            ->with('causer')
            ->where('project_id', $project->id)
            ->latest()
            ->paginate($perPage);

        return ActivityLogResource::collection($logs);
    }
}
