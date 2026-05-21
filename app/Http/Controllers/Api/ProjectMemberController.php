<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreMemberRequest;
use App\Http\Requests\Projects\UpdateMemberRequest;
use App\Http\Resources\ProjectMemberResource;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Http\Response;

class ProjectMemberController extends Controller
{
    public function __construct(protected ProjectService $projectService)
    {
    }

    public function index(Project $project)
    {
        $this->authorize('view', $project);

        $members = $project->members()->get();

        return ProjectMemberResource::collection($members);
    }

    public function store(StoreMemberRequest $request, Project $project): ProjectMemberResource
    {
        $this->authorize('manageMembers', $project);

        $data = $request->validated();
        $user = User::findOrFail($data['user_id']);

        $this->projectService->addMember($project, $user, $data['role'], $request->user());

        $fresh = $project->members()->where('users.id', $user->id)->first();

        return new ProjectMemberResource($fresh ?? $user);
    }

    public function update(UpdateMemberRequest $request, Project $project, User $user): ProjectMemberResource
    {
        $this->authorize('manageMembers', $project);

        $data = $request->validated();

        $this->projectService->updateMember($project, $user, $data['role'], $request->user());

        $fresh = $project->members()->where('users.id', $user->id)->first();

        return new ProjectMemberResource($fresh ?? $user);
    }

    public function destroy(Project $project, User $user): Response
    {
        $this->authorize('manageMembers', $project);

        $this->projectService->removeMember($project, $user, request()->user());

        return response()->noContent();
    }
}
