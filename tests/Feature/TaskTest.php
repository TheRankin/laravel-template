<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithApiAuth;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use InteractsWithApiAuth;
    use RefreshDatabase;

    /**
     * Convenience: build a team owned by $owner, a project led by $owner,
     * with $owner attached as a team and project member.
     *
     * @return array{0: Team, 1: Project}
     */
    protected function teamAndProjectFor(User $owner): array
    {
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);

        $project = Project::factory()->create([
            'team_id' => $team->id,
            'created_by' => $owner->id,
        ]);
        $project->members()->attach($owner->id, ['role' => 'lead']);

        return [$team, $project];
    }

    public function test_project_member_can_create_task(): void
    {
        $lead = User::factory()->create();
        [, $project] = $this->teamAndProjectFor($lead);

        $response = $this->actingAsToken($lead)
            ->postJson("/api/projects/{$project->id}/tasks", [
                'title' => 'Write tests',
                'description' => 'Cover the API surface',
                'priority' => 'high',
            ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
        $response->assertJsonPath('data.title', 'Write tests')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.reporter_id', $lead->id);

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'Write tests',
            'reporter_id' => $lead->id,
        ]);
    }

    public function test_non_member_cannot_list_project_tasks(): void
    {
        $lead = User::factory()->create();
        $stranger = User::factory()->create();
        [, $project] = $this->teamAndProjectFor($lead);

        $response = $this->actingAsToken($stranger)
            ->getJson("/api/projects/{$project->id}/tasks");

        $response->assertStatus(403);
    }

    public function test_task_filters_work(): void
    {
        $lead = User::factory()->create();
        $alice = User::factory()->create();
        [$team, $project] = $this->teamAndProjectFor($lead);

        $team->members()->attach($alice->id, ['role' => 'member']);
        $project->members()->attach($alice->id, ['role' => 'contributor']);

        // status=todo, priority=medium
        $todoTask = Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
            'status' => 'todo',
            'priority' => 'medium',
            'assignee_id' => null,
            'parent_id' => null,
            'due_date' => null,
            'title' => 'Greenfield setup',
        ]);

        // status=in_progress, priority=high, assigned to alice
        $highTask = Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
            'status' => 'in_progress',
            'priority' => 'high',
            'assignee_id' => $alice->id,
            'parent_id' => null,
            'due_date' => null,
            'title' => 'Schema audit',
        ]);

        // overdue (in_progress, due_date in past) — priority forced to medium so it doesn't pollute priority filter
        $overdueTask = Task::factory()->overdue()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
            'assignee_id' => null,
            'parent_id' => null,
            'priority' => 'medium',
            'title' => 'Overdue chore',
        ]);

        // subtask of $todoTask — to test root filter
        $subtask = Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
            'status' => 'todo',
            'priority' => 'low',
            'parent_id' => $todoTask->id,
            'title' => 'Greenfield subtask',
        ]);

        $headers = $this->actingAsToken($lead);

        // status=todo → todoTask + subtask
        $byStatus = $headers->getJson("/api/projects/{$project->id}/tasks?status=todo");
        $byStatus->assertStatus(200);
        $statusIds = collect($byStatus->json('data'))->pluck('id')->all();
        $this->assertContains($todoTask->id, $statusIds);
        $this->assertContains($subtask->id, $statusIds);
        $this->assertNotContains($highTask->id, $statusIds);

        // priority=high → only highTask
        $byPriority = $headers->getJson("/api/projects/{$project->id}/tasks?priority=high");
        $byPriority->assertStatus(200);
        $priorityIds = collect($byPriority->json('data'))->pluck('id')->all();
        $this->assertSame([$highTask->id], array_values($priorityIds));

        // assignee_id=alice → only highTask
        $byAssignee = $headers->getJson("/api/projects/{$project->id}/tasks?assignee_id={$alice->id}");
        $byAssignee->assertStatus(200);
        $assigneeIds = collect($byAssignee->json('data'))->pluck('id')->all();
        $this->assertSame([$highTask->id], array_values($assigneeIds));

        // overdue=1 → only overdue task
        $byOverdue = $headers->getJson("/api/projects/{$project->id}/tasks?overdue=1");
        $byOverdue->assertStatus(200);
        $overdueIds = collect($byOverdue->json('data'))->pluck('id')->all();
        $this->assertContains($overdueTask->id, $overdueIds);
        $this->assertNotContains($todoTask->id, $overdueIds);

        // root=1 → excludes subtask
        $byRoot = $headers->getJson("/api/projects/{$project->id}/tasks?root=1");
        $byRoot->assertStatus(200);
        $rootIds = collect($byRoot->json('data'))->pluck('id')->all();
        $this->assertNotContains($subtask->id, $rootIds);
        $this->assertContains($todoTask->id, $rootIds);

        // q=Greenfield matches todoTask + subtask
        $bySearch = $headers->getJson("/api/projects/{$project->id}/tasks?q=Greenfield");
        $bySearch->assertStatus(200);
        $searchIds = collect($bySearch->json('data'))->pluck('id')->all();
        $this->assertContains($todoTask->id, $searchIds);
        $this->assertContains($subtask->id, $searchIds);
        $this->assertNotContains($highTask->id, $searchIds);
    }

    public function test_task_pagination(): void
    {
        $lead = User::factory()->create();
        [, $project] = $this->teamAndProjectFor($lead);

        Task::factory()->count(25)->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
            'parent_id' => null,
        ]);

        $page1 = $this->actingAsToken($lead)
            ->getJson("/api/projects/{$project->id}/tasks");

        $page1->assertStatus(200);
        $this->assertCount(20, $page1->json('data'));
        $this->assertSame(25, $page1->json('meta.total'));
        $this->assertNotNull($page1->json('links.next'));

        $page2 = $this->actingAsToken($lead)
            ->getJson("/api/projects/{$project->id}/tasks?page=2");

        $page2->assertStatus(200);
        $this->assertCount(5, $page2->json('data'));
    }

    public function test_status_transition_todo_to_in_progress_works(): void
    {
        $lead = User::factory()->create();
        [, $project] = $this->teamAndProjectFor($lead);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
            'status' => 'todo',
            'completed_at' => null,
        ]);

        $response = $this->actingAsToken($lead)
            ->postJson("/api/tasks/{$task->id}/status", [
                'status' => 'in_progress',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertSame('in_progress', $task->fresh()->status);
    }

    public function test_invalid_status_transition_returns_422(): void
    {
        $lead = User::factory()->create();
        [, $project] = $this->teamAndProjectFor($lead);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
            'status' => 'in_progress',
            'completed_at' => null,
        ]);

        // in_progress → done is NOT allowed (must go through in_review first).
        $response = $this->actingAsToken($lead)
            ->postJson("/api/tasks/{$task->id}/status", [
                'status' => 'done',
            ]);

        $response->assertStatus(422);
        $this->assertSame('in_progress', $task->fresh()->status);
    }

    public function test_done_status_sets_completed_at(): void
    {
        $lead = User::factory()->create();
        [, $project] = $this->teamAndProjectFor($lead);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
            'status' => 'in_progress',
            'completed_at' => null,
        ]);

        // in_progress → in_review
        $this->actingAsToken($lead)
            ->postJson("/api/tasks/{$task->id}/status", ['status' => 'in_review'])
            ->assertStatus(200);

        // in_review → done
        $response = $this->actingAsToken($lead)
            ->postJson("/api/tasks/{$task->id}/status", ['status' => 'done']);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'done');

        $fresh = $task->fresh();
        $this->assertSame('done', $fresh->status);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_leaving_done_clears_completed_at(): void
    {
        $lead = User::factory()->create();
        [, $project] = $this->teamAndProjectFor($lead);

        $task = Task::factory()->done()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
        ]);

        // done → in_progress (reopen)
        $response = $this->actingAsToken($lead)
            ->postJson("/api/tasks/{$task->id}/status", ['status' => 'in_progress']);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'in_progress');

        $fresh = $task->fresh();
        $this->assertSame('in_progress', $fresh->status);
        $this->assertNull($fresh->completed_at);
    }

    public function test_assigning_task_logs_activity_and_notifies_assignee(): void
    {
        $lead = User::factory()->create();
        $assignee = User::factory()->create();
        [$team, $project] = $this->teamAndProjectFor($lead);

        $team->members()->attach($assignee->id, ['role' => 'member']);
        $project->members()->attach($assignee->id, ['role' => 'contributor']);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
            'assignee_id' => null,
        ]);

        $response = $this->actingAsToken($lead)
            ->postJson("/api/tasks/{$task->id}/assign", [
                'user_id' => $assignee->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.assignee_id', $assignee->id);

        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => Task::class,
            'subject_id' => $task->id,
            'action' => 'task.assigned',
            'causer_id' => $lead->id,
        ]);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $assignee->id,
            'type' => 'task.assigned',
        ]);
    }

    public function test_sync_labels_replaces_label_set(): void
    {
        $lead = User::factory()->create();
        [, $project] = $this->teamAndProjectFor($lead);

        $labelA = Label::factory()->create(['project_id' => $project->id, 'name' => 'A']);
        $labelB = Label::factory()->create(['project_id' => $project->id, 'name' => 'B']);
        $labelC = Label::factory()->create(['project_id' => $project->id, 'name' => 'C']);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
        ]);

        // Start with A and B attached.
        $task->labels()->sync([$labelA->id, $labelB->id]);

        // Sync to only C — should replace.
        $response = $this->actingAsToken($lead)
            ->postJson("/api/tasks/{$task->id}/labels", [
                'label_ids' => [$labelC->id],
            ]);

        $response->assertStatus(200);

        $attached = $task->fresh()->labels()->pluck('labels.id')->all();
        $this->assertEqualsCanonicalizing([$labelC->id], $attached);
    }

    public function test_subtasks_inherit_project(): void
    {
        $lead = User::factory()->create();
        [, $project] = $this->teamAndProjectFor($lead);

        $parent = Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
            'parent_id' => null,
        ]);

        $response = $this->actingAsToken($lead)
            ->postJson("/api/tasks/{$parent->id}/subtasks", [
                'title' => 'Child task',
            ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
        $response->assertJsonPath('data.parent_id', $parent->id)
            ->assertJsonPath('data.project_id', $project->id);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Child task',
            'project_id' => $project->id,
            'parent_id' => $parent->id,
        ]);
    }

    public function test_reorder_updates_position_and_renumbers_siblings(): void
    {
        $lead = User::factory()->create();
        [, $project] = $this->teamAndProjectFor($lead);

        // Three sibling root tasks at positions 1,2,3.
        $a = Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
            'parent_id' => null,
            'position' => 1,
        ]);
        $b = Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
            'parent_id' => null,
            'position' => 2,
        ]);
        $c = Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
            'parent_id' => null,
            'position' => 3,
        ]);

        // Move C to position 1 — should push A and B down.
        $response = $this->actingAsToken($lead)
            ->postJson("/api/tasks/{$c->id}/reorder", ['position' => 1]);

        $response->assertStatus(200)
            ->assertJsonPath('data.position', 1);

        $this->assertSame(1, $c->fresh()->position);
        $this->assertSame(2, $a->fresh()->position);
        $this->assertSame(3, $b->fresh()->position);
    }
}
