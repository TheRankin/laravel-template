<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $regularUsers = User::factory()->count(4)->create([
            'role' => 'member',
        ]);

        $allUsers = collect([$admin])->merge($regularUsers);

        $teams = collect();
        for ($t = 0; $t < 2; $t++) {
            $owner = $allUsers->random();
            $name = fake()->unique()->company();

            $team = Team::factory()->create([
                'name' => $name,
                'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
                'owner_id' => $owner->id,
            ]);

            $team->members()->syncWithoutDetaching([
                $owner->id => ['role' => 'owner'],
            ]);

            $otherMembers = $allUsers->where('id', '!=', $owner->id)->random(min(3, $allUsers->count() - 1));
            foreach ($otherMembers as $m) {
                $team->members()->syncWithoutDetaching([
                    $m->id => ['role' => fake()->randomElement(['admin', 'member', 'member'])],
                ]);
            }

            $teams->push($team);
        }

        foreach ($teams as $team) {
            $teamMemberIds = $team->members()->pluck('users.id')->all();

            $projectCount = random_int(2, 4);
            for ($p = 0; $p < $projectCount; $p++) {
                $creatorId = collect($teamMemberIds)->random();

                $project = Project::factory()->create([
                    'team_id' => $team->id,
                    'key' => Str::upper(Str::random(4)),
                    'created_by' => $creatorId,
                ]);

                $project->members()->syncWithoutDetaching([
                    $creatorId => ['role' => 'lead'],
                ]);
                foreach (collect($teamMemberIds)->diff([$creatorId]) as $uid) {
                    $project->members()->syncWithoutDetaching([
                        $uid => ['role' => fake()->randomElement(['contributor', 'contributor', 'viewer'])],
                    ]);
                }

                $labels = collect();
                for ($l = 0; $l < 8; $l++) {
                    $labels->push(Label::factory()->create([
                        'project_id' => $project->id,
                        'name' => Str::lower(fake()->unique()->word()).'-'.Str::lower(Str::random(3)),
                    ]));
                }

                $rootTasks = collect();
                for ($i = 0; $i < 20; $i++) {
                    $assigneeId = fake()->boolean(70) ? collect($teamMemberIds)->random() : null;
                    $reporterId = collect($teamMemberIds)->random();

                    $task = Task::factory()->create([
                        'project_id' => $project->id,
                        'assignee_id' => $assigneeId,
                        'reporter_id' => $reporterId,
                        'position' => $i,
                    ]);

                    if ($task->labels()->count() === 0) {
                        $labelIds = $labels->random(min(2, $labels->count()))->pluck('id')->all();
                        $task->labels()->sync($labelIds);
                    }

                    $rootTasks->push($task);

                    if (fake()->boolean(30) && $rootTasks->isNotEmpty()) {
                        Task::factory()->count(random_int(1, 3))->create([
                            'project_id' => $project->id,
                            'parent_id' => $task->id,
                            'reporter_id' => $reporterId,
                            'assignee_id' => fake()->boolean(60) ? collect($teamMemberIds)->random() : null,
                        ]);
                    }

                    if (fake()->boolean(30)) {
                        Comment::factory()->count(random_int(1, 3))->create([
                            'task_id' => $task->id,
                            'user_id' => collect($teamMemberIds)->random(),
                        ]);
                    }

                    ActivityLog::factory()->create([
                        'causer_id' => $reporterId,
                        'subject_type' => Task::class,
                        'subject_id' => $task->id,
                        'action' => 'task.created',
                        'team_id' => $team->id,
                        'project_id' => $project->id,
                    ]);
                }
            }
        }
    }
}
