<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithApiAuth;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use InteractsWithApiAuth;
    use RefreshDatabase;

    public function test_assignee_receives_notification_on_assignment(): void
    {
        $lead = User::factory()->create();
        $assignee = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $lead->id]);
        $team->members()->attach($lead->id, ['role' => 'owner']);
        $team->members()->attach($assignee->id, ['role' => 'member']);

        $project = Project::factory()->create([
            'team_id' => $team->id,
            'created_by' => $lead->id,
        ]);
        $project->members()->attach($lead->id, ['role' => 'lead']);
        $project->members()->attach($assignee->id, ['role' => 'contributor']);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
            'assignee_id' => null,
        ]);

        $response = $this->actingAsToken($lead)
            ->postJson("/api/tasks/{$task->id}/assign", ['user_id' => $assignee->id]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $assignee->id,
            'type' => 'task.assigned',
        ]);
    }

    public function test_user_can_mark_notification_read(): void
    {
        $user = User::factory()->create();
        $notification = AppNotification::factory()->create([
            'user_id' => $user->id,
            'read_at' => null,
        ]);

        $response = $this->actingAsToken($user)
            ->postJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(200);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_read(): void
    {
        $user = User::factory()->create();

        AppNotification::factory()->count(3)->create([
            'user_id' => $user->id,
            'read_at' => null,
        ]);
        AppNotification::factory()->create([
            'user_id' => $user->id,
            'read_at' => now(),
        ]);

        $response = $this->actingAsToken($user)
            ->postJson('/api/notifications/read-all');

        $response->assertStatus(200)
            ->assertJsonPath('updated', 3);

        $unread = AppNotification::where('user_id', $user->id)->whereNull('read_at')->count();
        $this->assertSame(0, $unread);
    }

    public function test_user_cannot_act_on_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $notification = AppNotification::factory()->create([
            'user_id' => $other->id,
            'read_at' => null,
        ]);

        $response = $this->actingAsToken($owner)
            ->postJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(403);

        $this->assertNull($notification->fresh()->read_at);
    }
}
