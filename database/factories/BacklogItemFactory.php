<?php

namespace Database\Factories;

use App\Enums\BacklogPriority;
use App\Enums\BacklogStatus;
use App\Models\BacklogItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BacklogItem>
 */
class BacklogItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => 'Draft work',
            'description' => null,
            'priority' => BacklogPriority::Medium,
            'status' => BacklogStatus::Todo,
            'created_by' => User::factory(),
            'archived_at' => null,
        ];
    }

    /**
     * Indicate that the item is archived and therefore read only.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
