<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithApiAuth;
use Tests\TestCase;

class SearchAndDashboardTest extends TestCase
{
    use InteractsWithApiAuth;
    use RefreshDatabase;

    public function test_dashboard_returns_my_task_counts_and_upcoming_and_recent_activity(): void
    {
        $user = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $user->id]);
        $team->members()->attach($user->id, ['role' => 'owner']);

        $project = Project::factory()->create([
            'team_id' => $team->id,
            'created_by' => $user->id,
        ]);
        $project->members()->attach($user->id, ['role' => 'lead']);

        // My tasks: one todo, one in_progress, one done.
        Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $user->id,
            'assignee_id' => $user->id,
            'status' => 'todo',
            'due_date' => null,
            'completed_at' => null,
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $user->id,
            'assignee_id' => $user->id,
            'status' => 'in_progress',
            'due_date' => null,
            'completed_at' => null,
        ]);
        Task::factory()->done()->create([
            'project_id' => $project->id,
            'reporter_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        // An upcoming task — due within 7 days.
        Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $user->id,
            'assignee_id' => $user->id,
            'status' => 'in_progress',
            'due_date' => now()->addDays(2)->format('Y-m-d'),
            'completed_at' => null,
        ]);

        // An overdue task.
        Task::factory()->overdue()->create([
            'project_id' => $project->id,
            'reporter_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        // Some activity caused by the user.
        ActivityLog::factory()->count(2)->create([
            'causer_id' => $user->id,
            'project_id' => $project->id,
            'subject_type' => Project::class,
            'subject_id' => $project->id,
        ]);

        $response = $this->actingAsToken($user)->getJson('/api/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'my_tasks_by_status' => ['todo', 'in_progress', 'in_review', 'done', 'cancelled'],
                'upcoming',
                'overdue_count',
                'recent_activity',
            ]);

        $byStatus = $response->json('my_tasks_by_status');
        $this->assertSame(1, $byStatus['todo']);
        // in_progress: the bare one, the upcoming (in_progress, due in 2 days),
        // and the overdue() state task (in_progress).
        $this->assertSame(3, $byStatus['in_progress']);
        $this->assertSame(1, $byStatus['done']);

        $this->assertGreaterThanOrEqual(1, count($response->json('upcoming')));
        $this->assertSame(1, $response->json('overdue_count'));
        $this->assertGreaterThanOrEqual(1, count($response->json('recent_activity')));
    }

    public function test_search_returns_matching_projects_tasks_and_comments_for_accessible_only(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $user->id]);
        $team->members()->attach($user->id, ['role' => 'owner']);

        $project = Project::factory()->create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'name' => 'Quokka Marketing',
        ]);
        $project->members()->attach($user->id, ['role' => 'lead']);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $user->id,
            'title' => 'Find a quokka mascot',
        ]);

        $comment = Comment::factory()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'body' => 'Should we put a quokka on the homepage?',
        ]);

        // A stranger's project containing "quokka" too — must NOT be visible to $user.
        $strangerTeam = Team::factory()->create(['owner_id' => $stranger->id]);
        $strangerTeam->members()->attach($stranger->id, ['role' => 'owner']);
        $strangerProject = Project::factory()->create([
            'team_id' => $strangerTeam->id,
            'created_by' => $stranger->id,
            'name' => 'Stranger Quokka Stuff',
        ]);
        Task::factory()->create([
            'project_id' => $strangerProject->id,
            'reporter_id' => $stranger->id,
            'title' => 'Stranger quokka thing',
        ]);

        $response = $this->actingAsToken($user)->getJson('/api/search?q=quokka');

        $response->assertStatus(200)
            ->assertJsonStructure(['projects', 'tasks', 'comments']);

        $projectIds = collect($response->json('projects'))->pluck('id')->all();
        $taskIds = collect($response->json('tasks'))->pluck('id')->all();
        $commentIds = collect($response->json('comments'))->pluck('id')->all();

        $this->assertContains($project->id, $projectIds);
        $this->assertNotContains($strangerProject->id, $projectIds);
        $this->assertContains($task->id, $taskIds);
        $this->assertContains($comment->id, $commentIds);
    }
}
