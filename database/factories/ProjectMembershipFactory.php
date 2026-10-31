<?php

namespace Database\Factories;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMembership>
 */
class ProjectMembershipFactory extends Factory
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
            'user_id' => User::factory(),
            'project_role' => ProjectRole::Manager,
        ];
    }

    /**
     * Indicate that the member may view but not change the project.
     */
    public function viewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'project_role' => ProjectRole::Viewer,
        ]);
    }

    /**
     * Indicate that the member takes part but does not manage the project.
     */
    public function member(): static
    {
        return $this->state(fn (array $attributes) => [
            'project_role' => ProjectRole::Member,
        ]);
    }
}
