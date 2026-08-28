<?php

namespace Database\Factories;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Todo>
 */
class TodoFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Todo>
     */
    protected $model = Todo::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'is_completed' => false,
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'due_date' => fake()->optional(0.7)->dateTimeBetween('now', '+2 weeks'),
            'completed_at' => null,
        ];
    }

    /**
     * Indicate that the todo is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the todo is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_completed' => false,
            'completed_at' => null,
        ]);
    }

    /**
     * Set high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }
}
