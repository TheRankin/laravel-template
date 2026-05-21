<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\StoreTeamRequest;
use App\Http\Requests\Teams\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Services\TeamService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TeamController extends Controller
{
    public function __construct(protected TeamService $teamService)
    {
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $teams = $request->user()
            ->teams()
            ->withCount(['members', 'projects'])
            ->orderBy('teams.id')
            ->paginate($perPage);

        return TeamResource::collection($teams);
    }

    public function store(StoreTeamRequest $request): TeamResource
    {
        $this->authorize('create', Team::class);

        $team = $this->teamService->create($request->validated(), $request->user());
        $team->loadCount(['members', 'projects']);

        return new TeamResource($team);
    }

    public function show(Team $team): TeamResource
    {
        $this->authorize('view', $team);

        $team->load('members')->loadCount(['members', 'projects']);

        return new TeamResource($team);
    }

    public function update(UpdateTeamRequest $request, Team $team): TeamResource
    {
        $this->authorize('update', $team);

        $team->fill($request->validated());
        $team->save();
        $team->loadCount(['members', 'projects']);

        return new TeamResource($team);
    }

    public function destroy(Team $team): Response
    {
        $this->authorize('delete', $team);

        $team->delete();

        return response()->noContent();
    }
}
