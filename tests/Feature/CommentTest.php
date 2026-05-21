<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithApiAuth;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use InteractsWithApiAuth;
    use RefreshDatabase;

    /**
     * @return array{0: Team, 1: Project, 2: Task}
     */
    protected function makeProjectWithTask(User $lead): array
    {
        $team = Team::factory()->create(['owner_id' => $lead->id]);
        $team->members()->attach($lead->id, ['role' => 'owner']);

        $project = Project::factory()->create([
            'team_id' => $team->id,
            'created_by' => $lead->id,
        ]);
        $project->members()->attach($lead->id, ['role' => 'lead']);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
        ]);

        return [$team, $project, $task];
    }

    public function test_project_member_can_post_comment(): void
    {
        $lead = User::factory()->create();
        [, , $task] = $this->makeProjectWithTask($lead);

        $response = $this->actingAsToken($lead)
            ->postJson("/api/tasks/{$task->id}/comments", [
                'body' => 'Looks good to me.',
            ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
        $response->assertJsonPath('data.body', 'Looks good to me.')
            ->assertJsonPath('data.user_id', $lead->id);

        $this->assertDatabaseHas('comments', [
            'task_id' => $task->id,
            'user_id' => $lead->id,
            'body' => 'Looks good to me.',
        ]);
    }

    public function test_only_author_can_edit_comment(): void
    {
        $lead = User::factory()->create();
        $teammate = User::factory()->create();
        [$team, $project, $task] = $this->makeProjectWithTask($lead);

        $team->members()->attach($teammate->id, ['role' => 'member']);
        $project->members()->attach($teammate->id, ['role' => 'contributor']);

        $comment = Comment::factory()->create([
            'task_id' => $task->id,
            'user_id' => $lead->id,
            'body' => 'Original body',
        ]);

        // teammate is a project member but NOT the author — must fail.
        $foreign = $this->actingAsToken($teammate)
            ->patchJson("/api/comments/{$comment->id}", [
                'body' => 'Hijacked!',
            ]);

        $foreign->assertStatus(403);
        $this->assertSame('Original body', $comment->fresh()->body);

        // Author can edit.
        $owner = $this->actingAsToken($lead)
            ->patchJson("/api/comments/{$comment->id}", [
                'body' => 'Polished body',
            ]);

        $owner->assertStatus(200)
            ->assertJsonPath('data.body', 'Polished body');

        $this->assertSame('Polished body', $comment->fresh()->body);
        $this->assertNotNull($comment->fresh()->edited_at);
    }

    public function test_author_can_delete_own_comment(): void
    {
        $lead = User::factory()->create();
        [, , $task] = $this->makeProjectWithTask($lead);

        $comment = Comment::factory()->create([
            'task_id' => $task->id,
            'user_id' => $lead->id,
        ]);

        $response = $this->actingAsToken($lead)
            ->deleteJson("/api/comments/{$comment->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }
}
