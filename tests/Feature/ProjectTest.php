<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithApiAuth;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use InteractsWithApiAuth;
    use RefreshDatabase;

    /**
     * Helper: create a team owned by a user with that user attached as the
     * "owner" team-member pivot.
     */
    protected function teamOwnedBy(User $user): Team
    {
        $team = Team::factory()->create(['owner_id' => $user->id]);
        $team->members()->attach($user->id, ['role' => 'owner']);

        return $team;
    }

    /**
     * Helper: create a project whose creator is `lead` and team owner is owner.
     */
    protected function projectLedBy(User $user, ?Team $team = null): Project
    {
        $team = $team ?? $this->teamOwnedBy($user);

        $project = Project::factory()->create([
            'team_id' => $team->id,
            'created_by' => $user->id,
        ]);

        $project->members()->attach($user->id, ['role' => 'lead']);

        return $project;
    }

    public function test_team_owner_can_create_project_and_becomes_lead(): void
    {
        $owner = User::factory()->create();
        $team = $this->teamOwnedBy($owner);

        $response = $this->actingAsToken($owner)
            ->postJson("/api/teams/{$team->id}/projects", [
                'name' => 'Website Redesign',
                'key' => 'WEB',
                'description' => 'New web project',
            ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
        $response->assertJsonPath('data.name', 'Website Redesign')
            ->assertJsonPath('data.key', 'WEB')
            ->assertJsonPath('data.team_id', $team->id);

        $projectId = $response->json('data.id');

        $this->assertDatabaseHas('project_members', [
            'project_id' => $projectId,
            'user_id' => $owner->id,
            'role' => 'lead',
        ]);
    }

    public function test_only_team_members_see_team_projects(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $team = $this->teamOwnedBy($owner);

        $project = Project::factory()->create([
            'team_id' => $team->id,
            'created_by' => $owner->id,
        ]);
        $project->members()->attach($owner->id, ['role' => 'lead']);

        // Outsider cannot view the team's projects index — fails the TeamPolicy@view check.
        $response = $this->actingAsToken($outsider)
            ->getJson("/api/teams/{$team->id}/projects");

        $response->assertStatus(403);

        // Owner can list and sees the project.
        $ownerResponse = $this->actingAsToken($owner)
            ->getJson("/api/teams/{$team->id}/projects");

        $ownerResponse->assertStatus(200);
        $ids = collect($ownerResponse->json('data'))->pluck('id')->all();
        $this->assertContains($project->id, $ids);
    }

    public function test_project_lead_can_update_project(): void
    {
        $lead = User::factory()->create();
        $project = $this->projectLedBy($lead);

        $response = $this->actingAsToken($lead)
            ->patchJson("/api/projects/{$project->id}", [
                'name' => 'Renamed Project',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Renamed Project');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Renamed Project',
        ]);
    }

    public function test_project_lead_can_archive_and_restore_project(): void
    {
        $lead = User::factory()->create();
        $project = $this->projectLedBy($lead);

        $archive = $this->actingAsToken($lead)
            ->postJson("/api/projects/{$project->id}/archive");

        $archive->assertStatus(200)
            ->assertJsonPath('data.status', 'archived');

        $restore = $this->actingAsToken($lead)
            ->postJson("/api/projects/{$project->id}/restore");

        $restore->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_project_stats_endpoint_returns_expected_shape(): void
    {
        $lead = User::factory()->create();
        $project = $this->projectLedBy($lead);

        // Seed a few tasks of different statuses to make counts non-trivial.
        Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
            'status' => 'todo',
            'priority' => 'low',
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
            'status' => 'done',
            'priority' => 'high',
            'completed_at' => now(),
        ]);
        Task::factory()->overdue()->create([
            'project_id' => $project->id,
            'reporter_id' => $lead->id,
        ]);

        $response = $this->actingAsToken($lead)
            ->getJson("/api/projects/{$project->id}/stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'tasks_total',
                    'tasks_by_status',
                    'tasks_by_priority',
                    'overdue',
                    'completed',
                    'completion_pct',
                    'members',
                ],
            ]);

        $this->assertSame(3, $response->json('data.tasks_total'));
        $this->assertSame(1, $response->json('data.completed'));
        $this->assertSame(1, $response->json('data.overdue'));
    }

    public function test_project_lead_can_add_and_remove_members(): void
    {
        $lead = User::factory()->create();
        $teammate = User::factory()->create();

        $team = $this->teamOwnedBy($lead);
        // Teammate also belongs to the team so they're visible.
        $team->members()->attach($teammate->id, ['role' => 'member']);

        $project = $this->projectLedBy($lead, $team);

        $add = $this->actingAsToken($lead)
            ->postJson("/api/projects/{$project->id}/members", [
                'user_id' => $teammate->id,
                'role' => 'contributor',
            ]);

        $this->assertContains($add->getStatusCode(), [200, 201]);

        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $teammate->id,
            'role' => 'contributor',
        ]);

        $remove = $this->actingAsToken($lead)
            ->deleteJson("/api/projects/{$project->id}/members/{$teammate->id}");

        $remove->assertStatus(204);

        $this->assertDatabaseMissing('project_members', [
            'project_id' => $project->id,
            'user_id' => $teammate->id,
        ]);
    }

    public function test_cannot_remove_last_lead(): void
    {
        $lead = User::factory()->create();
        $project = $this->projectLedBy($lead);

        $response = $this->actingAsToken($lead)
            ->deleteJson("/api/projects/{$project->id}/members/{$lead->id}");

        $response->assertStatus(422);

        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $lead->id,
            'role' => 'lead',
        ]);
    }
}
