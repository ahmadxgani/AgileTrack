<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\SprintReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_builds_a_sign_in_able_demo(): void
    {
        $this->seed();

        // The administrator the README documents.
        $admin = User::query()->where('email', 'test@example.com')->sole();

        $this->assertTrue($admin->is_administrator);
        $this->assertTrue(Hash::check('password', $admin->password));

        // Everybody else may sign in too, and the demo covers the roles.
        $this->assertSame(7, User::query()->count());
        $this->assertSame(7, User::query()->whereHas('roles')->count());

        $this->assertSame(1, Project::query()->count());
        $this->assertSame(6, Project::query()->sole()->memberships()->count());

        // Two sprints, one closed and one active, both planned with items.
        $this->assertSame(1, Sprint::query()->where('status', 'closed')->count());
        $this->assertSame(1, Sprint::query()->where('status', 'active')->count());

        // Both reviews are decided, and the needs-changes one filed work.
        $this->assertSame(1, SprintReview::query()->where('decision', 'approved')->count());
        $this->assertSame(1, SprintReview::query()
            ->where('decision', 'needs_changes')
            ->whereNotNull('follow_up_backlog_item_id')
            ->count());
    }

    public function test_seeder_stays_inert_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->seed();

        $this->assertSame(0, User::query()->count());
    }

    public function test_seeder_can_run_twice_without_colliding(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(7, User::query()->count());
        $this->assertSame(1, Project::query()->count());
    }
}
