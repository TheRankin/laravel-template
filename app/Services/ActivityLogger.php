<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public function log(
        string $action,
        Model $subject,
        ?User $causer = null,
        array $properties = [],
        ?int $teamId = null,
        ?int $projectId = null
    ): ActivityLog {
        return ActivityLog::create([
            'causer_id' => $causer?->id,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'properties' => $properties,
            'team_id' => $teamId,
            'project_id' => $projectId,
        ]);
    }

    public function forTask(Task $task, string $action, ?User $causer = null, array $properties = []): ActivityLog
    {
        $teamId = $task->project?->team_id;
        $projectId = $task->project_id;

        return $this->log($action, $task, $causer, $properties, $teamId, $projectId);
    }

    public function forProject(Project $project, string $action, ?User $causer = null, array $properties = []): ActivityLog
    {
        return $this->log($action, $project, $causer, $properties, $project->team_id, $project->id);
    }

    public function forTeam(Team $team, string $action, ?User $causer = null, array $properties = []): ActivityLog
    {
        return $this->log($action, $team, $causer, $properties, $team->id, null);
    }
}
