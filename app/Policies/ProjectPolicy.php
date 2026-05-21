<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;

class ProjectPolicy
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
     * Anyone authenticated may list — controllers scope to the user's projects.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Project members, project leads, or team owners/admins may view.
     */
    public function view(User $user, Project $project): bool
    {
        return $this->isProjectMember($user, $project)
            || $this->isTeamOwnerOrAdmin($user, $project->team ?? null, $project->team_id);
    }

    /**
     * Anyone authenticated may attempt project creation; team membership is
     * enforced in the controller against the parent team (TeamPolicy@view).
     */
    public function create(User $user, ?Team $team = null): bool
    {
        if ($team) {
            return $this->isTeamMember($user, $team);
        }

        return true;
    }

    /**
     * Project leads or team owners/admins may update.
     */
    public function update(User $user, Project $project): bool
    {
        return $this->isProjectLead($user, $project)
            || $this->isTeamOwnerOrAdmin($user, $project->team ?? null, $project->team_id);
    }

    /**
     * Project leads or team owners/admins may delete.
     */
    public function delete(User $user, Project $project): bool
    {
        return $this->isProjectLead($user, $project)
            || $this->isTeamOwnerOrAdmin($user, $project->team ?? null, $project->team_id);
    }

    /**
     * Project leads or team owners/admins may archive/restore.
     */
    public function archive(User $user, Project $project): bool
    {
        return $this->isProjectLead($user, $project)
            || $this->isTeamOwnerOrAdmin($user, $project->team ?? null, $project->team_id);
    }

    /**
     * Project leads or team owners/admins may manage members.
     */
    public function manageMembers(User $user, Project $project): bool
    {
        return $this->isProjectLead($user, $project)
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

    protected function isTeamMember(User $user, Team $team): bool
    {
        if ($team->owner_id === $user->getKey()) {
            return true;
        }

        return $team->members()
            ->where('user_id', $user->getKey())
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
