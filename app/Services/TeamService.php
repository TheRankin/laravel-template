<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamService
{
    public function __construct(
        protected ActivityLogger $activityLogger,
    ) {
    }

    public function create(array $data, User $owner): Team
    {
        return DB::transaction(function () use ($data, $owner) {
            $base = Str::slug($data['name'] ?? 'team');
            if ($base === '') {
                $base = 'team';
            }

            $slug = $base;
            $i = 1;
            while (Team::where('slug', $slug)->exists()) {
                $slug = $base . '-' . $i;
                $i++;
            }

            $data['slug'] = $slug;
            $data['owner_id'] = $owner->id;

            $team = Team::create($data);

            $team->members()->syncWithoutDetaching([
                $owner->id => ['role' => 'owner'],
            ]);

            $this->activityLogger->forTeam($team, 'team.created', $owner);

            return $team;
        });
    }

    public function inviteMember(Team $team, string $email, string $role, User $actor): User
    {
        return DB::transaction(function () use ($team, $email, $role, $actor) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                $local = Str::before($email, '@');
                $user = User::create([
                    'name' => $local !== '' ? $local : 'user',
                    'email' => $email,
                    'password' => Hash::make(Str::random(40)),
                ]);
            }

            $team->members()->syncWithoutDetaching([
                $user->id => ['role' => $role],
            ]);

            $this->activityLogger->forTeam($team, 'team.member_invited', $actor, [
                'user_id' => $user->id,
                'email' => $email,
                'role' => $role,
            ]);

            return $user;
        });
    }

    public function removeMember(Team $team, User $user, User $actor): void
    {
        DB::transaction(function () use ($team, $user, $actor) {
            $existing = $team->members()->where('users.id', $user->id)->first();
            $existingRole = $existing?->pivot?->role;

            if ($existingRole === 'owner') {
                throw ValidationException::withMessages([
                    'user' => 'Cannot remove the team owner.',
                ]);
            }

            $team->members()->detach($user->id);

            $this->activityLogger->forTeam($team, 'team.member_removed', $actor, [
                'user_id' => $user->id,
            ]);
        });
    }

    public function updateMember(Team $team, User $user, string $role, User $actor): void
    {
        DB::transaction(function () use ($team, $user, $role, $actor) {
            $team->members()->updateExistingPivot($user->id, ['role' => $role]);

            $this->activityLogger->forTeam($team, 'team.member_updated', $actor, [
                'user_id' => $user->id,
                'role' => $role,
            ]);
        });
    }
}
