<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class SearchService
{
    public function search(User $user, string $q): array
    {
        $q = trim($q);

        if ($q === '') {
            return [
                'projects' => [],
                'tasks' => [],
                'comments' => [],
            ];
        }

        $like = '%' . $q . '%';

        $teamIds = $user->teams()->pluck('teams.id')->all();
        $directProjectIds = $user->projectMemberships()->pluck('projects.id')->all();

        $accessibleProjectIds = Project::query()
            ->where(function ($query) use ($teamIds, $directProjectIds) {
                if (! empty($teamIds)) {
                    $query->whereIn('team_id', $teamIds);
                }
                if (! empty($directProjectIds)) {
                    $query->orWhereIn('id', $directProjectIds);
                }
            })
            ->pluck('id')
            ->all();

        if (empty($accessibleProjectIds)) {
            return [
                'projects' => [],
                'tasks' => [],
                'comments' => [],
            ];
        }

        $projects = Project::query()
            ->whereIn('id', $accessibleProjectIds)
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('key', 'like', $like);
            })
            ->limit(10)
            ->get()
            ->all();

        $tasks = Task::query()
            ->whereIn('project_id', $accessibleProjectIds)
            ->where(function ($query) use ($like) {
                $query->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like);
            })
            ->limit(10)
            ->get()
            ->all();

        $comments = Comment::query()
            ->whereHas('task', function ($query) use ($accessibleProjectIds) {
                $query->whereIn('project_id', $accessibleProjectIds);
            })
            ->where('body', 'like', $like)
            ->limit(10)
            ->get()
            ->all();

        return [
            'projects' => $projects,
            'tasks' => $tasks,
            'comments' => $comments,
        ];
    }
}
