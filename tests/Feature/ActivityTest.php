<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithApiAuth;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use InteractsWithApiAuth;
    use RefreshDatabase;

    /**
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

    public function test_creating_task_writes_activity_log(): void
    {
        $lead = User::factory()->create();
        [, $project] = $this->teamAndProjectFor($lead);

        $response = $this->actingAsToken($lead)
            ->postJson("/api/projects/{$project->id}/tasks", [
                'title' => 'Log me',
            ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
        $taskId = $response->json('data.id');

        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => Task::class,
            'subject_id' => $taskId,
            'action' => 'task.created',
            'causer_id' => $lead->id,
        ]);
    }

    public function test_changing_status_writes_activity_log_with_from_to(): void
    {
        $lead = User::factory()->create();
        [, $project] = $this->teamAndProjectFor($lead);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
            'status' => 'todo',
        ]);

        $response = $this->actingAsToken($lead)
            ->postJson("/api/tasks/{$task->id}/status", ['status' => 'in_progress']);

        $response->assertStatus(200);

        $log = ActivityLog::where('subject_type', Task::class)
            ->where('subject_id', $task->id)
            ->where('action', 'task.status_changed')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('todo', $log->properties['from'] ?? null);
        $this->assertSame('in_progress', $log->properties['to'] ?? null);
    }

    public function test_project_activity_endpoint_lists_project_entries_only(): void
    {
        $lead = User::factory()->create();
        [, $project] = $this->teamAndProjectFor($lead);

        // Set up a second project to make sure entries don't leak.
        [, $otherProject] = $this->teamAndProjectFor($lead);

        // Create some entries scoped to project.
        ActivityLog::factory()->count(3)->create([
            'project_id' => $project->id,
            'causer_id' => $lead->id,
            'subject_type' => Project::class,
            'subject_id' => $project->id,
        ]);

        ActivityLog::factory()->count(2)->create([
            'project_id' => $otherProject->id,
            'causer_id' => $lead->id,
            'subject_type' => Project::class,
            'subject_id' => $otherProject->id,
        ]);

        $response = $this->actingAsToken($lead)
            ->getJson("/api/projects/{$project->id}/activity");

        $response->assertStatus(200);

        $rows = $response->json('data');
        foreach ($rows as $row) {
            $this->assertSame($project->id, $row['project_id']);
        }
    }
}
