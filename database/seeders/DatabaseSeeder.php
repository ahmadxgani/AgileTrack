<?php

namespace Database\Seeders;

use App\Enums\BacklogPriority;
use App\Enums\BacklogStatus;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\ReviewDecision;
use App\Enums\UserRole;
use App\Models\BacklogItem;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Development-only demo data.
 *
 * Never reached in production: the early return below keeps every account
 * and every demo record out of a production database, so no default
 * credentials exist there.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Password of every seeded demo account, local development only.
     *
     * This constant is deliberately loud: it is a well-known password for
     * throwaway accounts that only exist on a developer machine. The seeder
     * refuses to run in production, and nobody should ever reuse these
     * accounts anywhere else.
     */
    private const DEMO_PASSWORD = 'password';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        // The demo is created once. Running it again over the same database
        // would only collide on unique emails, so stop before touching a
        // database that already holds it.
        if (User::query()->where('email', 'test@example.com')->exists()) {
            $this->command?->warn('Demo data already seeded; skipping.');

            return;
        }

        $this->user('Test User', 'test@example.com', [UserRole::Administrator]);
        $manager = $this->user('Maya Manager', 'manager@example.com', [UserRole::ProjectManager]);
        $owner = $this->user('Oki Owner', 'owner@example.com', [UserRole::Owner]);
        $analyst = $this->user('Budi Analyst', 'analyst@example.com', [UserRole::BusinessAnalyst]);
        $developer = $this->user('Dewi Developer', 'developer@example.com', [UserRole::TeamMember]);
        $tester = $this->user('Tari Tester', 'qa@example.com', [UserRole::QaEngineer]);
        $customer = $this->user('Cici Customer', 'customer@example.com', [UserRole::Customer]);

        $project = Project::factory()->create([
            'name' => 'AgileTrack Demo',
            'description' => 'Sample project seeded for local evaluation.',
            'status' => ProjectStatus::Active,
            'starts_at' => '2026-10-01',
            'ends_at' => '2026-12-31',
            'created_by' => $manager->id,
        ]);

        $project->memberships()->create(['user_id' => $manager->id, 'project_role' => ProjectRole::Manager]);
        $project->memberships()->create(['user_id' => $developer->id, 'project_role' => ProjectRole::Member]);
        $project->memberships()->create(['user_id' => $owner->id, 'project_role' => ProjectRole::Viewer]);
        $project->memberships()->create(['user_id' => $tester->id, 'project_role' => ProjectRole::Member]);
        $project->memberships()->create(['user_id' => $analyst->id, 'project_role' => ProjectRole::Member]);
        $project->memberships()->create(['user_id' => $customer->id, 'project_role' => ProjectRole::Viewer]);

        $item = fn (string $title, BacklogPriority $priority) => BacklogItem::factory()->create([
            'project_id' => $project->id,
            'title' => $title,
            'priority' => $priority,
            'created_by' => $manager->id,
        ]);

        // The closed sprint: finished work, kept for the history.
        $closedSprint = Sprint::factory()->closed()->create([
            'project_id' => $project->id,
            'name' => 'Sprint 1',
            'goal' => 'Accounts, sign in and the first project pages.',
            'starts_at' => '2026-10-01',
            'ends_at' => '2026-10-14',
        ]);

        $done = [
            $item('Sign in and sign out', BacklogPriority::High),
            $item('Administrator manages accounts', BacklogPriority::High),
            $item('Create a project and invite members', BacklogPriority::High),
        ];

        foreach ($done as $index => $backlogItem) {
            $backlogItem->forceFill([
                'sprint_id' => $closedSprint->id,
                'status' => BacklogStatus::Done,
            ])->save();

            $backlogItem->assignees()->attach($index === 1 ? $tester->id : $developer->id);
        }

        // The active sprint: work in flight across the board columns.
        $activeSprint = Sprint::factory()->active()->create([
            'project_id' => $project->id,
            'name' => 'Sprint 2',
            'goal' => 'Backlog, sprints and the Kanban board.',
            'starts_at' => '2026-10-15',
            'ends_at' => '2026-10-28',
        ]);

        $inFlight = [
            $item('Edit a backlog item and its assignees', BacklogPriority::Urgent),
            $item('Move a card between Kanban columns', BacklogPriority::High),
            $item('Plan a sprint and close it', BacklogPriority::Medium),
            $item('See the dashboard summary', BacklogPriority::Medium),
        ];

        foreach ($inFlight as $index => $backlogItem) {
            $backlogItem->forceFill([
                'sprint_id' => $activeSprint->id,
                'status' => $index === 0 ? BacklogStatus::Done
                    : ($index === 1 ? BacklogStatus::InProgress : BacklogStatus::Todo),
            ])->save();

            $backlogItem->assignees()->attach($index % 2 === 0 ? $developer->id : $tester->id);
        }

        // Two items stay in the backlog pool, unplanned.
        $item('Write the sprint review notes', BacklogPriority::Low);
        $item('Archive an old project', BacklogPriority::Low);

        // One approved review, decided by the project manager: the policy
        // lets managers decide without a customer decision flow existing yet.
        $project->reviews()->create([
            'sprint_id' => $closedSprint->id,
            'scheduled_at' => '2026-10-15 10:00:00',
            'notes' => 'Sprint 1 demo: sign in, account administration and projects.',
            'feedback' => 'The team can start working; account invites need a clearer error message.',
            'decision' => ReviewDecision::Approved,
            'created_by' => $manager->id,
        ])->recordDecision(ReviewDecision::Approved, $manager);

        // One review asking for changes; the model files the follow-up item.
        $project->reviews()->create([
            'sprint_id' => null,
            'scheduled_at' => '2026-10-29 10:00:00',
            'notes' => 'Sprint 2 demo: backlog, Kanban and dashboard.',
            'feedback' => 'Card moves are slow when many items are on the board.',
            'decision' => ReviewDecision::Pending,
            'created_by' => $manager->id,
        ])->recordDecision(
            ReviewDecision::NeedsChanges,
            $manager,
            'Show a loading state while a card move is saved',
        );
    }

    /**
     * Create a demo account with the given roles.
     *
     * @param  list<UserRole>  $roles
     */
    private function user(string $name, string $email, array $roles): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => self::DEMO_PASSWORD,
        ]);

        foreach ($roles as $role) {
            $user->roles()->create(['role' => $role]);
        }

        return $user;
    }
}
