<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\AssignTaskRequest;
use App\Http\Requests\Tasks\IndexTaskRequest;
use App\Http\Requests\Tasks\ReorderTaskRequest;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\SyncLabelsRequest;
use App\Http\Requests\Tasks\TransitionStatusRequest;
use App\Http\Requests\Tasks\UpdatePriorityRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\TaskListResource;
use App\Http\Resources\TaskResource;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    public function __construct(
        protected TaskService $taskService,
        protected ActivityLogger $activityLogger,
    ) {
    }

    public function index(IndexTaskRequest $request, Project $project)
    {
        $this->authorize('viewAny', [Task::class, $project]);

        $filters = $request->validated();

        $query = $project->tasks()
            ->with(['assignee', 'reporter'])
            ->withCount(['comments', 'attachments', 'subtasks as children_count']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (array_key_exists('assignee_id', $filters) && $filters['assignee_id'] !== null) {
            $query->where('assignee_id', $filters['assignee_id']);
        }

        if (! empty($filters['due_before'])) {
            $query->whereDate('due_date', '<=', $filters['due_before']);
        }

        if (! empty($filters['due_after'])) {
            $query->whereDate('due_date', '>=', $filters['due_after']);
        }

        if (! empty($filters['overdue'])) {
            $query->overdue();
        }

        if (array_key_exists('parent_id', $filters) && $filters['parent_id'] !== null) {
            $query->where('parent_id', $filters['parent_id']);
        } elseif (! empty($filters['root'])) {
            $query->whereNull('parent_id');
        }

        if (! empty($filters['label_id'])) {
            $labelId = $filters['label_id'];
            $query->whereHas('labels', function ($q) use ($labelId) {
                $q->where('labels.id', $labelId);
            });
        }

        if (! empty($filters['q'])) {
            $term = '%' . $filters['q'] . '%';
            $query->where(function ($sub) use ($term) {
                $sub->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        $sortable = ['created_at', 'due_date', 'priority', 'position', 'title'];
        $sort = $filters['sort'] ?? 'position';
        if (! in_array($sort, $sortable, true)) {
            $sort = 'position';
        }

        $dir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $dir);

        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min(100, $perPage));

        return TaskListResource::collection($query->paginate($perPage));
    }

    public function store(StoreTaskRequest $request, Project $project): TaskResource
    {
        $this->authorize('create', [Task::class, $project]);

        $task = $this->taskService->create($project, $request->validated(), $request->user());
        $task->load(['assignee', 'reporter', 'labels'])
            ->loadCount(['comments', 'attachments', 'subtasks as children_count']);

        return new TaskResource($task);
    }

    public function show(Task $task): TaskResource
    {
        $this->authorize('view', $task);

        $task->load(['assignee', 'reporter', 'labels'])
            ->loadCount(['comments', 'attachments', 'subtasks as children_count']);

        return new TaskResource($task);
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        $task = $this->taskService->update($task, $request->validated(), $request->user());
        $task->load(['assignee', 'reporter', 'labels'])
            ->loadCount(['comments', 'attachments', 'subtasks as children_count']);

        return new TaskResource($task);
    }

    public function destroy(Task $task, Request $request): Response
    {
        $this->authorize('delete', $task);

        $this->taskService->delete($task, $request->user());

        return response()->noContent();
    }

    public function assign(AssignTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('assign', $task);

        $user = User::findOrFail($request->validated()['user_id']);

        $task = $this->taskService->assign($task, $user, $request->user());
        $task->load(['assignee', 'reporter', 'labels'])
            ->loadCount(['comments', 'attachments', 'subtasks as children_count']);

        return new TaskResource($task);
    }

    public function unassign(Task $task, Request $request): TaskResource
    {
        $this->authorize('assign', $task);

        $task = $this->taskService->assign($task, null, $request->user());
        $task->load(['assignee', 'reporter', 'labels'])
            ->loadCount(['comments', 'attachments', 'subtasks as children_count']);

        return new TaskResource($task);
    }

    public function transition(TransitionStatusRequest $request, Task $task): TaskResource
    {
        $this->authorize('transition', $task);

        $task = $this->taskService->transitionStatus(
            $task,
            $request->validated()['status'],
            $request->user()
        );
        $task->load(['assignee', 'reporter', 'labels'])
            ->loadCount(['comments', 'attachments', 'subtasks as children_count']);

        return new TaskResource($task);
    }

    public function priority(UpdatePriorityRequest $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        $newPriority = $request->validated()['priority'];
        $oldPriority = $task->priority;

        $task->priority = $newPriority;
        $task->save();

        if ($oldPriority !== $newPriority) {
            $this->activityLogger->forTask($task, 'task.priority_changed', $request->user(), [
                'from' => $oldPriority,
                'to' => $newPriority,
            ]);
        }

        $task->load(['assignee', 'reporter', 'labels'])
            ->loadCount(['comments', 'attachments', 'subtasks as children_count']);

        return new TaskResource($task);
    }

    public function syncLabels(SyncLabelsRequest $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        $task = $this->taskService->syncLabels(
            $task,
            $request->validated()['label_ids'],
            $request->user()
        );
        $task->load(['assignee', 'reporter', 'labels'])
            ->loadCount(['comments', 'attachments', 'subtasks as children_count']);

        return new TaskResource($task);
    }

    public function subtasks(Task $task)
    {
        $this->authorize('view', $task);

        $subtasks = $task->subtasks()
            ->with(['assignee', 'reporter'])
            ->withCount(['comments', 'attachments', 'subtasks as children_count'])
            ->orderBy('position')
            ->get();

        return TaskListResource::collection($subtasks);
    }

    public function storeSubtask(StoreTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('create', [Task::class, $task->project]);

        $data = $request->validated();
        $data['parent_id'] = $task->id;

        $project = $task->project ?? Project::findOrFail($task->project_id);

        $subtask = $this->taskService->create($project, $data, $request->user());
        $subtask->load(['assignee', 'reporter', 'labels'])
            ->loadCount(['comments', 'attachments', 'subtasks as children_count']);

        return new TaskResource($subtask);
    }

    public function reorder(ReorderTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        $task = $this->taskService->reorder($task, (int) $request->validated()['position']);
        $task->load(['assignee', 'reporter', 'labels'])
            ->loadCount(['comments', 'attachments', 'subtasks as children_count']);

        return new TaskResource($task);
    }

    public function activity(Task $task, Request $request)
    {
        $this->authorize('view', $task);

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $logs = ActivityLog::where('subject_type', Task::class)
            ->where('subject_id', $task->id)
            ->with('causer')
            ->latest()
            ->paginate($perPage);

        return ActivityLogResource::collection($logs);
    }
}
