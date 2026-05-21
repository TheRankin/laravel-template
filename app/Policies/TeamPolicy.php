<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
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
     * Any authenticated user can list teams (the controller filters to their own).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Only team members may view a team.
     */
    public function view(User $user, Team $team): bool
    {
        return $this->isMember($user, $team);
    }

    /**
     * Anyone authenticated may create a team.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Only the owner or an admin team member may update.
     */
    public function update(User $user, Team $team): bool
    {
        return $this->isOwnerOrAdmin($user, $team);
    }

    /**
     * Only the team owner may delete.
     */
    public function delete(User $user, Team $team): bool
    {
        return $team->owner_id === $user->getKey();
    }

    /**
     * Only the team owner or admin may manage members.
     */
    public function manageMembers(User $user, Team $team): bool
    {
        return $this->isOwnerOrAdmin($user, $team);
    }

    protected function isMember(User $user, Team $team): bool
    {
        if ($team->owner_id === $user->getKey()) {
            return true;
        }

        return $team->members()
            ->where('user_id', $user->getKey())
            ->exists();
    }

    protected function isOwnerOrAdmin(User $user, Team $team): bool
    {
        if ($team->owner_id === $user->getKey()) {
            return true;
        }

        return $team->members()
            ->where('user_id', $user->getKey())
            ->whereIn('team_members.role', ['owner', 'admin'])
            ->exists();
    }
}
