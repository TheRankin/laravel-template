<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;

class TaskPolicy
{
    /**
     * Admin override — applies before all policy checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return null;
    }

    /**
     * Any authenticated user may attempt to list — the controller scopes to
     * a project the user can see.
     */
    public function viewAny(User $user, ?Project $project = null): bool
    {
        if ($project) {
            return $this->isProjectMember($user, $project)
                || $this->isTeamOwnerOrAdmin($user, $project->team ?? null, $project->team_id);
        }

        return true;
    }

    /**
     * Project members and team owners/admins may view tasks.
     */
    public function view(User $user, Task $task): bool
    {
        $project = $task->project ?? Project::find($task->project_id);

        if (! $project) {
            return false;
        }

        return $this->isProjectMember($user, $project)
            || $this->isTeamOwnerOrAdmin($user, $project->team ?? null, $project->team_id);
    }

    /**
     * Project members may create tasks (and team owners/admins).
     */
    public function create(User $user, ?Project $project = null): bool
    {
        if (! $project) {
            return true;
        }

        return $this->isProjectMember($user, $project)
            || $this->isTeamOwnerOrAdmin($user, $project->team ?? null, $project->team_id);
    }

    /**
     * Only the assignee, reporter, project lead, or team owner/admin may edit.
     */
    public function update(User $user, Task $task): bool
    {
        if ($task->assignee_id === $user->getKey() || $task->reporter_id === $user->getKey()) {
            return true;
        }

        $project = $task->project ?? Project::find($task->project_id);

        if (! $project) {
            return false;
        }

        return $this->isProjectLead($user, $project)
            || $this->isTeamOwnerOrAdmin($user, $project->team ?? null, $project->team_id);
    }

    /**
     * Only the assignee, reporter, project lead, or team owner/admin may delete.
     */
    public function delete(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    /**
     * Status transitions are gated the same as update.
     */
    public function transition(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    /**
     * Assigning a task is gated the same as update.
     */
    public function assign(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    protected function isProjectMember(User $user, Project $project): bool
    {
        return $project->members()
            ->where('user_id', $user->getKey())
            ->exists();
    }

    protected function isProjectLead(User $user, Project $project): bool
    {
        return $project->members()
            ->where('user_id', $user->getKey())
            ->wherePivot('role', 'lead')
            ->exists();
    }

    protected function isTeamOwnerOrAdmin(User $user, ?Team $team, ?int $teamId = null): bool
    {
        if (! $team && $teamId) {
            $team = Team::find($teamId);
        }

        if (! $team) {
            return false;
        }

        if ($team->owner_id === $user->getKey()) {
            return true;
        }

        return $team->members()
            ->where('user_id', $user->getKey())
            ->whereIn('team_members.role', ['owner', 'admin'])
            ->exists();
    }
}
