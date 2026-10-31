<?php

namespace Tests\Feature\Dashboard;

use App\Enums\BacklogStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Models\BacklogItem;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\Sprint;
use App\Models\SprintReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_only_visible_projects_and_live_item_counts(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['name' => 'Alpha', 'status' => ProjectStatus::Active]);
        $other = Project::factory()->create(['name' => 'Secret']);
        ProjectMembership::factory()->for($project)->for($user)->create(['project_role' => ProjectRole::Member]);

        BacklogItem::factory()->for($project)->count(2)->create(['status' => BacklogStatus::Todo]);
        BacklogItem::factory()->for($project)->count(3)->create(['status' => BacklogStatus::InProgress]);
        BacklogItem::factory()->for($project)->count(4)->create(['status' => BacklogStatus::Done]);
        BacklogItem::factory()->for($project)->archived()->count(5)->create(['status' => BacklogStatus::Todo]);
        BacklogItem::factory()->for($other)->count(6)->create(['status' => BacklogStatus::Todo]);
        Sprint::factory()->for($project)->active()->create(['name' => 'Ship it']);
        $soon = now()->addDays(2)->startOfMinute();
        SprintReview::factory()->for($project)->create(['scheduled_at' => $soon]);
        SprintReview::factory()->for($project)->create(['scheduled_at' => $soon->copy()->addDays(3)]);
        SprintReview::factory()->for($project)->create(['scheduled_at' => now()->subDay()]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('projects', 1)
                ->where('summary.projects', 1)
                ->where('projects.0.id', $project->id)
                ->where('projects.0.name', 'Alpha')
                ->where('projects.0.status', ProjectStatus::Active->value)
                ->where('projects.0.active_sprint', 'Ship it')
                ->where('projects.0.counts', ['todo' => 2, 'in_progress' => 3, 'done' => 4])
                ->where('projects.0.next_review_at', $soon->toIso8601String()));
    }

    public function test_projects_without_items_sprints_or_reviews_have_zero_and_null_summaries(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();
        ProjectMembership::factory()->for($project)->for($user)->create();

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('projects.0.counts', ['todo' => 0, 'in_progress' => 0, 'done' => 0])
            ->where('projects.0.active_sprint', null)
            ->where('projects.0.next_review_at', null));
    }

    public function test_an_administrator_sees_every_project_including_archived_ones(): void
    {
        $admin = User::factory()->administrator()->create();
        Project::factory()->create(['name' => 'Alpha']);
        $archived = Project::factory()->archived()->create(['name' => 'Beta']);

        $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->has('projects', 2)
            ->where('summary.projects', 2)
            ->where('projects.1.archived_at', $archived->archived_at->toIso8601String()));
    }

    public function test_the_dashboard_query_count_does_not_grow_per_project(): void
    {
        $user = User::factory()->create();
        $this->addProjectFor($user);

        $one = $this->dashboardQueries($user, 1);

        $this->addProjectFor($user);
        $this->addProjectFor($user);

        $three = $this->dashboardQueries($user, 3);

        // The page needs one list plus three set-based lookups. Keep a small
        // allowance for the session and account middleware, but adding two
        // projects must not add two more per-project lookup sequences.
        $this->assertLessThanOrEqual(10, $one);
        $this->assertLessThanOrEqual(10, $three);
        $this->assertSame($one, $three);
    }

    private function addProjectFor(User $user): void
    {
        $project = Project::factory()->create();
        ProjectMembership::factory()->for($project)->for($user)->create();
        BacklogItem::factory()->for($project)->create();
        Sprint::factory()->for($project)->active()->create();
        SprintReview::factory()->for($project)->create(['scheduled_at' => now()->addDay()]);
    }

    private function dashboardQueries(User $user, int $projectCount): int
    {
        // Use a fresh account so each request pays the same role lookup.
        $this->actingAs($user->fresh());
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('projects', $projectCount));

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
