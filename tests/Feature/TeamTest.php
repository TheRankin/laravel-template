<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InteractsWithApiAuth;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use InteractsWithApiAuth;
    use RefreshDatabase;

    public function test_user_can_create_team_and_is_owner_member(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsToken($user)->postJson('/api/teams', [
            'name' => 'Acme Inc',
            'slug' => 'acme-inc',
            'description' => 'A demo team',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
        $response->assertJsonPath('data.name', 'Acme Inc')
            ->assertJsonPath('data.owner_id', $user->id);

        $teamId = $response->json('data.id');

        $this->assertDatabaseHas('teams', [
            'id' => $teamId,
            'owner_id' => $user->id,
        ]);

        $this->assertDatabaseHas('team_members', [
            'team_id' => $teamId,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_user_lists_only_their_teams(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // The user owns one and is a member of another via the service flow.
        $myTeam = Team::factory()->create(['owner_id' => $user->id]);
        $myTeam->members()->attach($user->id, ['role' => 'owner']);

        $foreignTeam = Team::factory()->create(['owner_id' => $otherUser->id]);
        $foreignTeam->members()->attach($otherUser->id, ['role' => 'owner']);

        $response = $this->actingAsToken($user)->getJson('/api/teams');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($myTeam->id, $ids);
        $this->assertNotContains($foreignTeam->id, $ids);
    }

    public function test_non_member_cannot_view_team(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);

        $response = $this->actingAsToken($stranger)->getJson("/api/teams/{$team->id}");

        $response->assertStatus(403);
    }

    public function test_owner_can_update_team(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id, 'name' => 'Old Name']);
        $team->members()->attach($owner->id, ['role' => 'owner']);

        $response = $this->actingAsToken($owner)->patchJson("/api/teams/{$team->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_non_owner_cannot_delete_team(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);
        $team->members()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAsToken($member)->deleteJson("/api/teams/{$team->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('teams', ['id' => $team->id]);
    }

    public function test_owner_can_invite_member(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);

        $response = $this->actingAsToken($owner)->postJson("/api/teams/{$team->id}/members", [
            'email' => 'newbie@example.com',
            'role' => 'member',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);

        $this->assertDatabaseHas('users', ['email' => 'newbie@example.com']);

        $invited = User::where('email', 'newbie@example.com')->first();

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $invited->id,
            'role' => 'member',
        ]);
    }

    public function test_owner_cannot_remove_owner_role_member(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $team->members()->attach($owner->id, ['role' => 'owner']);

        $response = $this->actingAsToken($owner)
            ->deleteJson("/api/teams/{$team->id}/members/{$owner->id}");

        // The service throws ValidationException → 422. Accept 403 as well in case
        // a future authorization layer intercepts it first.
        $this->assertContains($response->getStatusCode(), [422, 403]);

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
    }
}
