<?php

namespace Database\Factories;

use App\Enums\ReviewDecision;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\SprintReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SprintReview>
 */
class SprintReviewFactory extends Factory
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
            'sprint_id' => null,
            'scheduled_at' => null,
            'notes' => null,
            'feedback' => null,
            'decision' => ReviewDecision::Pending,
            'decided_by' => null,
            'decided_at' => null,
            'created_by' => User::factory(),
            'follow_up_backlog_item_id' => null,
        ];
    }

    /**
     * Indicate that the review covers the given sprint.
     */
    public function forSprint(Sprint $sprint): static
    {
        return $this->state(fn (array $attributes) => [
            'sprint_id' => $sprint->id,
            'project_id' => $sprint->project_id,
        ]);
    }
}
