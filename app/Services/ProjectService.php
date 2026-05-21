<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    public function __construct(
        protected ActivityLogger $activityLogger,
    ) {
    }

    public function create(Team $team, array $data, User $creator): Project
    {
        return DB::transaction(function () use ($team, $data, $creator) {
            $data['team_id'] = $team->id;
            $data['created_by'] = $creator->id;
            $data['status'] = $data['status'] ?? 'active';

            $project = Project::create($data);

            $project->members()->syncWithoutDetaching([
                $creator->id => ['role' => 'lead'],
            ]);

            $this->activityLogger->forProject($project, 'project.created', $creator);

            return $project;
        });
    }

    public function archive(Project $project, User $actor): Project
    {
        return DB::transaction(function () use ($project, $actor) {
            $project->status = 'archived';
            $project->save();

            $this->activityLogger->forProject($project, 'project.archived', $actor);

            return $project;
        });
    }

    public function restore(Project $project, User $actor): Project
    {
        return DB::transaction(function () use ($project, $actor) {
            $project->status = 'active';
            $project->save();

            $this->activityLogger->forProject($project, 'project.restored', $actor);

            return $project;
        });
    }

    public function addMember(Project $project, User $user, string $role, User $actor): void
    {
        DB::transaction(function () use ($project, $user, $role, $actor) {
            $project->members()->syncWithoutDetaching([
                $user->id => ['role' => $role],
            ]);

            $this->activityLogger->forProject($project, 'project.member_added', $actor, [
                'user_id' => $user->id,
                'role' => $role,
            ]);
        });
    }

    public function removeMember(Project $project, User $user, User $actor): void
    {
        DB::transaction(function () use ($project, $user, $actor) {
            $existing = $project->members()->where('users.id', $user->id)->first();
            $existingRole = $existing?->pivot?->role;

            if ($existingRole === 'lead') {
                $otherLeads = $project->members()
                    ->wherePivot('role', 'lead')
                    ->where('users.id', '!=', $user->id)
                    ->count();

                if ($otherLeads === 0) {
                    throw ValidationException::withMessages([
                        'user' => 'Cannot remove the only lead from the project.',
                    ]);
                }
            }

            $project->members()->detach($user->id);

            $this->activityLogger->forProject($project, 'project.member_removed', $actor, [
                'user_id' => $user->id,
            ]);
        });
    }

    public function updateMember(Project $project, User $user, string $role, User $actor): void
    {
        DB::transaction(function () use ($project, $user, $role, $actor) {
            $project->members()->updateExistingPivot($user->id, ['role' => $role]);

            $this->activityLogger->forProject($project, 'project.member_updated', $actor, [
                'user_id' => $user->id,
                'role' => $role,
            ]);
        });
    }

    public function stats(Project $project): array
    {
        $statuses = ['todo', 'in_progress', 'in_review', 'done', 'cancelled'];
        $priorities = ['low', 'medium', 'high', 'urgent'];

        $byStatus = array_fill_keys($statuses, 0);
        $byPriority = array_fill_keys($priorities, 0);

        $statusCounts = Task::where('project_id', $project->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        foreach ($statusCounts as $status => $total) {
            $byStatus[$status] = (int) $total;
        }

        $priorityCounts = Task::where('project_id', $project->id)
            ->selectRaw('priority, COUNT(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->all();

        foreach ($priorityCounts as $priority => $total) {
            $byPriority[$priority] = (int) $total;
        }

        $tasksTotal = array_sum($byStatus);
        $completed = $byStatus['done'];

        $overdue = Task::where('project_id', $project->id)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->whereNotIn('status', ['done', 'cancelled'])
            ->count();

        $completionPct = $tasksTotal > 0
            ? round(($completed / $tasksTotal) * 100, 2)
            : 0.0;

        $members = $project->members()->count();

        return [
            'tasks_total' => $tasksTotal,
            'tasks_by_status' => $byStatus,
            'tasks_by_priority' => $byPriority,
            'overdue' => $overdue,
            'completed' => $completed,
            'completion_pct' => (float) $completionPct,
            'members' => $members,
        ];
    }
}
