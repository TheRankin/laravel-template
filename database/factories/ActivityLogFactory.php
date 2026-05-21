<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    public function definition(): array
    {
        return [
            'causer_id' => User::factory(),
            'subject_type' => Task::class,
            'subject_id' => Task::factory(),
            'action' => fake()->randomElement([
                'task.created',
                'task.updated',
                'task.status_changed',
                'task.assigned',
                'comment.created',
                'project.archived',
            ]),
            'properties' => [],
            'team_id' => null,
            'project_id' => null,
        ];
    }
}
