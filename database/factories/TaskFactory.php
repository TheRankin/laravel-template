<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        $statuses = ['todo', 'in_progress', 'in_review', 'done', 'cancelled'];
        $priorities = ['low', 'medium', 'high', 'urgent'];

        $status = fake()->randomElement($statuses);

        return [
            'project_id' => Project::factory(),
            'parent_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => $status,
            'priority' => fake()->randomElement($priorities),
            'assignee_id' => null,
            'reporter_id' => User::factory(),
            'due_date' => fake()->optional()->dateTimeBetween('-10 days', '+30 days')?->format('Y-m-d'),
            'completed_at' => $status === 'done' ? now() : null,
            'position' => fake()->numberBetween(0, 100),
        ];
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'done',
            'completed_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'due_date' => now()->subDays(3)->format('Y-m-d'),
            'completed_at' => null,
        ]);
    }
}
