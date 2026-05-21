<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;

class AttachmentPolicy
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
     * Project members may list attachments on a task.
     */
    public function viewAny(User $user, ?Task $task = null): bool
    {
        if (! $task) {
            return true;
        }

        return $this->canAccessTask($user, $task);
    }

    /**
     * Project members may view individual attachments.
     */
    public function view(User $user, Attachment $attachment): bool
    {
        $task = $attachment->task ?? Task::find($attachment->task_id);

        if (! $task) {
            return false;
        }

        return $this->canAccessTask($user, $task);
    }

    /**
     * Project members may upload attachments.
     */
    public function create(User $user, ?Task $task = null): bool
    {
        if (! $task) {
            return true;
        }

        return $this->canAccessTask($user, $task);
    }

    /**
     * Attachments cannot be updated in place — but the route is left for
     * completeness and gated the same as delete.
     */
    public function update(User $user, Attachment $attachment): bool
    {
        return $this->delete($user, $attachment);
    }

    /**
     * The uploader or a project lead (or team owner/admin) may delete an attachment.
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        if ($attachment->uploaded_by === $user->getKey()) {
            return true;
        }

        $task = $attachment->task ?? Task::find($attachment->task_id);

        if (! $task) {
            return false;
        }

        $project = $task->project ?? Project::find($task->project_id);

        if (! $project) {
            return false;
        }

        return $this->isProjectLead($user, $project)
            || $this->isTeamOwnerOrAdmin($user, $project->team ?? null, $project->team_id);
    }

    protected function canAccessTask(User $user, Task $task): bool
    {
        $project = $task->project ?? Project::find($task->project_id);

        if (! $project) {
            return false;
        }

        return $this->isProjectMember($user, $project)
            || $this->isTeamOwnerOrAdmin($user, $project->team ?? null, $project->team_id);
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
