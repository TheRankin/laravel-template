<?php

namespace App\Services;

use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskService
{
    /**
     * Allowed status transitions.
     *
     * @var array<string, array<int, string>>
     */
    protected array $transitions = [
        'todo' => ['in_progress', 'cancelled'],
        'in_progress' => ['in_review', 'todo', 'cancelled'],
        'in_review' => ['done', 'in_progress', 'cancelled'],
        'done' => ['in_progress'],
        'cancelled' => ['todo'],
    ];

    public function __construct(
        protected ActivityLogger $activityLogger,
        protected NotificationService $notifications,
    ) {
    }

    public function create(Project $project, array $data, User $creator): Task
    {
        return DB::transaction(function () use ($project, $data, $creator) {
            $data['project_id'] = $project->id;
            $data['reporter_id'] = $data['reporter_id'] ?? $creator->id;

            $parentId = $data['parent_id'] ?? null;

            $maxQuery = Task::query()
                ->where('project_id', $project->id);

            if ($parentId === null) {
                $maxQuery->whereNull('parent_id');
            } else {
                $maxQuery->where('parent_id', $parentId);
            }

            $maxPosition = (int) $maxQuery->max('position');
            $data['position'] = $maxPosition + 1;

            $task = Task::create($data);

            $this->activityLogger->forTask($task, 'task.created', $creator);

            if (! empty($task->assignee_id) && $task->assignee_id !== $creator->id) {
                $assignee = User::find($task->assignee_id);
                if ($assignee) {
                    $this->notifications->notify($assignee, 'task.assigned', [
                        'task_id' => $task->id,
                        'project_id' => $task->project_id,
                        'actor_id' => $creator->id,
                    ]);
                }
            }

            return $task;
        });
    }

    public function update(Task $task, array $data, User $actor): Task
    {
        return DB::transaction(function () use ($task, $data, $actor) {
            $original = $task->getAttributes();
            $changed = [];

            $task->fill($data);

            foreach ($task->getDirty() as $field => $newValue) {
                $changed[$field] = [
                    'from' => $original[$field] ?? null,
                    'to' => $newValue,
                ];
            }

            $task->save();

            if (! empty($changed)) {
                $this->activityLogger->forTask($task, 'task.updated', $actor, ['changed' => $changed]);
            }

            return $task;
        });
    }

    public function transitionStatus(Task $task, string $status, User $actor): Task
    {
        $old = $task->status;

        if ($old === $status) {
            return $task;
        }

        $allowed = $this->transitions[$old] ?? [];
        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Invalid transition from {$old} to {$status}.",
            ]);
        }

        return DB::transaction(function () use ($task, $status, $actor, $old) {
            $task->status = $status;

            if ($status === 'done') {
                $task->completed_at = now();
            } elseif ($old === 'done') {
                $task->completed_at = null;
            }

            $task->save();

            $this->activityLogger->forTask($task, 'task.status_changed', $actor, [
                'from' => $old,
                'to' => $status,
            ]);

            $watchers = collect();
            if (! empty($task->assignee_id)) {
                $assignee = User::find($task->assignee_id);
                if ($assignee) {
                    $watchers->push($assignee);
                }
            }
            if (! empty($task->reporter_id)) {
                $reporter = User::find($task->reporter_id);
                if ($reporter) {
                    $watchers->push($reporter);
                }
            }

            $watchers = $watchers->unique('id');

            $this->notifications->notifyMany(
                $watchers,
                'task.status_changed',
                [
                    'task_id' => $task->id,
                    'project_id' => $task->project_id,
                    'from' => $old,
                    'to' => $status,
                    'actor_id' => $actor->id,
                ],
                $actor
            );

            return $task;
        });
    }

    public function assign(Task $task, ?User $user, User $actor): Task
    {
        return DB::transaction(function () use ($task, $user, $actor) {
            $task->assignee_id = $user?->id;
            $task->save();

            if ($user) {
                $this->activityLogger->forTask($task, 'task.assigned', $actor, [
                    'assignee_id' => $user->id,
                ]);

                if ($user->id !== $actor->id) {
                    $this->notifications->notify($user, 'task.assigned', [
                        'task_id' => $task->id,
                        'project_id' => $task->project_id,
                        'actor_id' => $actor->id,
                    ]);
                }
            } else {
                $this->activityLogger->forTask($task, 'task.unassigned', $actor);
            }

            return $task;
        });
    }

    public function syncLabels(Task $task, array $labelIds, User $actor): Task
    {
        return DB::transaction(function () use ($task, $labelIds, $actor) {
            $validIds = Label::where('project_id', $task->project_id)
                ->whereIn('id', $labelIds)
                ->pluck('id')
                ->all();

            $task->labels()->sync($validIds);

            $this->activityLogger->forTask($task, 'task.labels_synced', $actor, [
                'label_ids' => $validIds,
            ]);

            return $task;
        });
    }

    public function reorder(Task $task, int $position): Task
    {
        return DB::transaction(function () use ($task, $position) {
            $siblingsQuery = Task::query()
                ->where('project_id', $task->project_id)
                ->where('id', '!=', $task->id);

            if ($task->parent_id === null) {
                $siblingsQuery->whereNull('parent_id');
            } else {
                $siblingsQuery->where('parent_id', $task->parent_id);
            }

            $siblingsQuery->where('position', '>=', $position)
                ->increment('position');

            $task->position = $position;
            $task->save();

            $this->activityLogger->forTask($task, 'task.reordered', null, [
                'position' => $position,
            ]);

            return $task;
        });
    }

    public function delete(Task $task, User $actor): void
    {
        DB::transaction(function () use ($task, $actor) {
            $this->activityLogger->forTask($task, 'task.deleted', $actor);
            $task->delete();
        });
    }
}
