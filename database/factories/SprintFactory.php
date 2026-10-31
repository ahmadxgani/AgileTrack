<?php

namespace Database\Factories;

use App\Enums\SprintStatus;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Sprint>
 */
class SprintFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = Carbon::parse('2026-11-02');

        return [
            'project_id' => Project::factory(),
            'name' => fake()->unique()->words(2, true),
            'goal' => null,
            'starts_at' => $startsAt->toDateString(),
            'ends_at' => $startsAt->copy()->addDays(13)->toDateString(),
            'status' => SprintStatus::Planned,
        ];
    }

    /**
     * Indicate that the sprint is running.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SprintStatus::Active,
        ]);
    }

    /**
     * Indicate that the sprint is closed and read only.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SprintStatus::Closed,
        ]);
    }
}
