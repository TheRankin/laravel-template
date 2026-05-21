<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TaskListResource;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(protected SearchService $searchService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $q = (string) $request->input('q');

        $results = $this->searchService->search($request->user(), $q);

        $projects = collect($results['projects'] ?? [])
            ->map(fn ($p) => (new ProjectResource($p))->resolve($request))
            ->all();

        $tasks = collect($results['tasks'] ?? [])
            ->map(fn ($t) => (new TaskListResource($t))->resolve($request))
            ->all();

        $comments = collect($results['comments'] ?? [])
            ->map(fn ($c) => (new CommentResource($c))->resolve($request))
            ->all();

        return response()->json([
            'projects' => $projects,
            'tasks' => $tasks,
            'comments' => $comments,
        ]);
    }
}
