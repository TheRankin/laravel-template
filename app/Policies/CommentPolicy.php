<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;

class CommentPolicy
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
     * Project members may list comments — the controller passes the task.
     */
    public function viewAny(User $user, ?Task $task = null): bool
    {
        if (! $task) {
            return true;
        }

        return $this->canAccessTask($user, $task);
    }

    /**
     * Project members may view any individual comment on a task they belong to.
     */
    public function view(User $user, Comment $comment): bool
    {
        $task = $comment->task ?? Task::find($comment->task_id);

        if (! $task) {
            return false;
        }

        return $this->canAccessTask($user, $task);
    }

    /**
     * Project members may comment on tasks.
     */
    public function create(User $user, ?Task $task = null): bool
    {
        if (! $task) {
            return true;
        }

        return $this->canAccessTask($user, $task);
    }

    /**
     * Only the author may edit a comment.
     */
    public function update(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->getKey();
    }

    /**
     * The author or a project lead (or team owner/admin) may delete a comment.
     */
    public function delete(User $user, Comment $comment): bool
    {
        if ($comment->user_id === $user->getKey()) {
            return true;
        }

        $task = $comment->task ?? Task::find($comment->task_id);

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
