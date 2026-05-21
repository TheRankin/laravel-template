<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\InviteMemberRequest;
use App\Http\Requests\Teams\UpdateMemberRequest;
use App\Http\Resources\TeamMemberResource;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamService;
use Illuminate\Http\Response;

class TeamMemberController extends Controller
{
    public function __construct(protected TeamService $teamService)
    {
    }

    public function index(Team $team)
    {
        $this->authorize('view', $team);

        $members = $team->members()->get();

        return TeamMemberResource::collection($members);
    }

    public function store(InviteMemberRequest $request, Team $team): TeamMemberResource
    {
        $this->authorize('manageMembers', $team);

        $data = $request->validated();

        $user = $this->teamService->inviteMember(
            $team,
            $data['email'],
            $data['role'],
            $request->user()
        );

        $fresh = $team->members()->where('users.id', $user->id)->first();

        return new TeamMemberResource($fresh ?? $user);
    }

    public function update(UpdateMemberRequest $request, Team $team, User $user): TeamMemberResource
    {
        $this->authorize('manageMembers', $team);

        $data = $request->validated();

        $this->teamService->updateMember($team, $user, $data['role'], $request->user());

        $fresh = $team->members()->where('users.id', $user->id)->first();

        return new TeamMemberResource($fresh ?? $user);
    }

    public function destroy(Team $team, User $user): Response
    {
        $this->authorize('manageMembers', $team);

        $this->teamService->removeMember($team, $user, request()->user());

        return response()->noContent();
    }
}
