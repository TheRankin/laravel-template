<?php

namespace App\Policies;

use App\Models\Label;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;

class LabelPolicy
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
     * Project members may list labels.
     */
    public function viewAny(User $user, ?Project $project = null): bool
    {
        if (! $project) {
            return true;
        }

        return $this->isProjectMember($user, $project)
            || $this->isTeamOwnerOrAdmin($user, $project->team ?? null, $project->team_id);
    }

    /**
     * Project members may view a label.
     */
    public function view(User $user, Label $label): bool
    {
        $project = $label->project ?? Project::find($label->project_id);

        if (! $project) {
            return false;
        }

        return $this->isProjectMember($user, $project)
            || $this->isTeamOwnerOrAdmin($user, $project->team ?? null, $project->team_id);
    }

    /**
     * Only project leads (and team owners/admins) may create labels.
     */
    public function create(User $user, ?Project $project = null): bool
    {
        if (! $project) {
            return true;
        }

        return $this->isProjectLead($user, $project)
            || $this->isTeamOwnerOrAdmin($user, $project->team ?? null, $project->team_id);
    }

    /**
     * Only project leads (and team owners/admins) may update labels.
     */
    public function update(User $user, Label $label): bool
    {
        $project = $label->project ?? Project::find($label->project_id);

        if (! $project) {
            return false;
        }

        return $this->isProjectLead($user, $project)
            || $this->isTeamOwnerOrAdmin($user, $project->team ?? null, $project->team_id);
    }

    /**
     * Only project leads (and team owners/admins) may delete labels.
     */
    public function delete(User $user, Label $label): bool
    {
        return $this->update($user, $label);
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
