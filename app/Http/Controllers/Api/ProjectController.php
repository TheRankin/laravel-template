<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProjectStatsResource;
use App\Models\Project;
use App\Models\Team;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    public function __construct(protected ProjectService $projectService)
    {
    }

    public function index(Request $request, Team $team)
    {
        $this->authorize('view', $team);

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $user = $request->user();
        $userId = $user->getKey();

        $query = $team->projects()
            ->withCount(['members', 'tasks'])
            ->orderBy('id');

        $isTeamPrivileged = $team->owner_id === $userId
            || $team->members()
                ->where('user_id', $userId)
                ->whereIn('team_members.role', ['owner', 'admin'])
                ->exists();

        if (! $isTeamPrivileged && ! ($user->role === 'admin')) {
            $query->where(function ($sub) use ($userId) {
                $sub->whereHas('members', function ($member) use ($userId) {
                    $member->where('users.id', $userId);
                });
            });
        }

        return ProjectResource::collection($query->paginate($perPage));
    }

    public function store(StoreProjectRequest $request, Team $team): ProjectResource
    {
        $this->authorize('view', $team);
        $this->authorize('create', [Project::class, $team]);

        $project = $this->projectService->create($team, $request->validated(), $request->user());
        $project->loadCount(['members', 'tasks']);

        return new ProjectResource($project);
    }

    public function show(Project $project): ProjectResource
    {
        $this->authorize('view', $project);

        $project->load(['team', 'creator'])->loadCount(['members', 'tasks']);

        return new ProjectResource($project);
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        $project->fill($request->validated());
        $project->save();
        $project->loadCount(['members', 'tasks']);

        return new ProjectResource($project);
    }

    public function destroy(Project $project): Response
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->noContent();
    }

    public function archive(Project $project, Request $request): ProjectResource
    {
        $this->authorize('archive', $project);

        $project = $this->projectService->archive($project, $request->user());
        $project->loadCount(['members', 'tasks']);

        return new ProjectResource($project);
    }

    public function restore(Project $project, Request $request): ProjectResource
    {
        $this->authorize('archive', $project);

        $project = $this->projectService->restore($project, $request->user());
        $project->loadCount(['members', 'tasks']);

        return new ProjectResource($project);
    }

    public function stats(Project $project): ProjectStatsResource
    {
        $this->authorize('view', $project);

        return new ProjectStatsResource($this->projectService->stats($project));
    }
}
