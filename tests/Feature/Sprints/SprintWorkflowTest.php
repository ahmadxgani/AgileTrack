<?php

namespace Tests\Feature\Sprints;

use App\Enums\BacklogPriority;
use App\Enums\BacklogStatus;
use App\Enums\ProjectRole;
use App\Enums\SprintStatus;
use App\Http\Controllers\SprintController;
use App\Models\BacklogItem;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SprintWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_manager_creates_a_sprint(): void
    {
        $project = Project::factory()->create(['name' => 'AgileTrack']);
        $manager = $this->member($project, ProjectRole::Manager);

        $this->actingAs($manager)->post(route('sprints.store', $project), [
            'name' => 'Sprint 1',
            'goal' => 'Ship the sprint board.',
            'starts_at' => '2026-11-02',
            'ends_at' => '2026-11-15',
        ])->assertRedirect();

        $sprint = Sprint::query()->sole();

        $this->assertSame($project->id, $sprint->project_id);
        $this->assertSame('Sprint 1', $sprint->name);
        $this->assertSame('Ship the sprint board.', $sprint->goal);
        $this->assertSame('2026-11-02', $sprint->starts_at->format('Y-m-d'));
        $this->assertSame('2026-11-15', $sprint->ends_at->format('Y-m-d'));
        $this->assertSame(SprintStatus::Planned, $sprint->status);
    }

    public function test_sprint_requires_a_name_and_ordered_dates(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);

        $payload = [
            'name' => 'Sprint 1',
            'starts_at' => '2026-11-02',
            'ends_at' => '2026-11-15',
        ];

        $this->actingAs($manager)->post(route('sprints.store', $project), [
            ...$payload,
            'name' => '',
        ])->assertSessionHasErrors('name');

        // End before start is rejected.
        $this->actingAs($manager)->post(route('sprints.store', $project), [
            ...$payload,
            'ends_at' => '2026-11-01',
        ])->assertSessionHasErrors('ends_at');

        // So is an impossible date.
        $this->actingAs($manager)->post(route('sprints.store', $project), [
            ...$payload,
            'starts_at' => 'not-a-date',
        ])->assertSessionHasErrors('starts_at');

        $this->assertDatabaseCount('sprints', 0);

        // A one-day sprint is fine.
        $this->actingAs($manager)->post(route('sprints.store', $project), [
            ...$payload,
            'ends_at' => $payload['starts_at'],
        ])->assertRedirect();
    }

    public function test_partial_update_compares_against_the_stored_date(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->create([
            'starts_at' => '2026-11-02',
            'ends_at' => '2026-11-15',
        ]);

        // Moving the start past the stored end is rejected even though the
        // request carries no end date.
        $this->actingAs($manager)->patch(
            route('sprints.update', [$project, $sprint]),
            ['name' => $sprint->name, 'starts_at' => '2026-11-20'],
        )->assertSessionHasErrors('ends_at');

        // Moving the end before the stored start is rejected too.
        $this->actingAs($manager)->patch(
            route('sprints.update', [$project, $sprint]),
            ['name' => $sprint->name, 'ends_at' => '2026-10-30'],
        )->assertSessionHasErrors('ends_at');

        $this->actingAs($manager)->patch(
            route('sprints.update', [$project, $sprint]),
            ['name' => 'Renamed', 'goal' => 'New goal.'],
        )->assertRedirect(route('sprints.show', [$project, $sprint]));

        $sprint->refresh();

        $this->assertSame('Renamed', $sprint->name);
        $this->assertSame('New goal.', $sprint->goal);
        $this->assertSame('2026-11-02', $sprint->starts_at->format('Y-m-d'));
        $this->assertSame('2026-11-15', $sprint->ends_at->format('Y-m-d'));
    }

    public function test_one_sprint_per_project_is_active_at_a_time(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $running = Sprint::factory()->for($project)->active()->create();
        $planned = Sprint::factory()->for($project)->create();

        $this->actingAs($manager)->patch(
            route('sprints.start', [$project, $planned]),
        )->assertSessionHasErrors('sprint');

        $this->assertSame(SprintStatus::Planned, $planned->fresh()?->status);
        $this->assertSame(SprintStatus::Active, $running->fresh()?->status);

        // Closing the running one frees the slot.
        $this->actingAs($manager)->patch(
            route('sprints.close', [$project, $running]),
        )->assertRedirect(route('sprints.show', [$project, $running]));

        $this->actingAs($manager)->patch(
            route('sprints.start', [$project, $planned]),
        )->assertRedirect(route('sprints.show', [$project, $planned]));

        $this->assertSame(SprintStatus::Active, $planned->fresh()?->status);
    }

    public function test_manager_moves_items_in_and_out_of_a_planned_sprint(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->create();
        $item = BacklogItem::factory()->for($project)->create();

        $this->actingAs($manager)->post(
            route('sprints.items.store', [$project, $sprint]),
            ['backlog_item_id' => $item->id],
        )->assertRedirect(route('sprints.show', [$project, $sprint]));

        $this->assertSame($sprint->id, $item->fresh()?->sprint_id);

        $this->actingAs($manager)->delete(
            route('sprints.items.destroy', [$project, $sprint]),
            ['backlog_item_id' => $item->id],
        )->assertRedirect(route('sprints.show', [$project, $sprint]));

        $this->assertNull($item->fresh()?->sprint_id);
    }

    public function test_only_unplanned_items_of_the_project_join_a_sprint(): void
    {
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->create();
        $foreign = BacklogItem::factory()->for($otherProject)->create();
        $planned = BacklogItem::factory()->for($project)->create([
            'sprint_id' => Sprint::factory()->for($project)->create()->id,
        ]);
        $archived = BacklogItem::factory()->for($project)->archived()->create();

        foreach ([$foreign, $planned, $archived] as $item) {
            $this->actingAs($manager)->post(
                route('sprints.items.store', [$project, $sprint]),
                ['backlog_item_id' => $item->id],
            )->assertSessionHasErrors('backlog_item_id');
        }

        $this->assertNull($foreign->fresh()?->sprint_id);
        $this->assertNotSame($sprint->id, $planned->fresh()?->sprint_id);
        $this->assertNull($archived->fresh()?->sprint_id);
    }

    public function test_a_closed_sprint_rejects_item_changes(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->closed()->create();
        $member = BacklogItem::factory()->for($project)->create(['sprint_id' => $sprint->id]);
        $loose = BacklogItem::factory()->for($project)->create();

        $this->actingAs($manager)->post(
            route('sprints.items.store', [$project, $sprint]),
            ['backlog_item_id' => $loose->id],
        )->assertSessionHasErrors('backlog_item_id');

        $this->actingAs($manager)->delete(
            route('sprints.items.destroy', [$project, $sprint]),
            ['backlog_item_id' => $member->id],
        )->assertSessionHasErrors('backlog_item_id');

        $this->actingAs($manager)->patch(
            route('sprints.items.status', [$project, $sprint, $member]),
            ['status' => BacklogStatus::InProgress->value],
        )->assertSessionHasErrors('status');

        $this->assertNull($loose->fresh()?->sprint_id);
        $this->assertSame($sprint->id, $member->fresh()?->sprint_id);
        $this->assertSame(BacklogStatus::Todo, $member->fresh()?->status);
        $this->assertDatabaseCount('backlog_status_histories', 0);

        // The sprint itself is frozen as well.
        $this->actingAs($manager)->get(
            route('sprints.edit', [$project, $sprint]),
        )->assertForbidden();
        $this->actingAs($manager)->patch(
            route('sprints.close', [$project, $sprint]),
        )->assertForbidden();
    }

    public function test_sprint_of_another_project_is_not_reachable(): void
    {
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $foreign = Sprint::factory()->for($otherProject)->create();
        $foreignItem = BacklogItem::factory()->for($otherProject)->create([
            'sprint_id' => $foreign->id,
        ]);

        // Every verb resolves the sprint through the project in the URL, so
        // one of another project is a 404 rather than a readable id.
        $calls = [
            'show' => fn () => $this->get(route('sprints.show', [$project, $foreign])),
            'edit' => fn () => $this->get(route('sprints.edit', [$project, $foreign])),
            'start' => fn () => $this->patch(route('sprints.start', [$project, $foreign])),
            'close' => fn () => $this->patch(route('sprints.close', [$project, $foreign])),
            'update' => fn () => $this->patch(route('sprints.update', [$project, $foreign]), [
                'name' => 'Hijacked',
                'starts_at' => '2026-11-02',
                'ends_at' => '2026-11-15',
            ]),
            'items.store' => fn () => $this->post(
                route('sprints.items.store', [$project, $foreign]),
                ['backlog_item_id' => $foreignItem->id],
            ),
            'items.destroy' => fn () => $this->delete(
                route('sprints.items.destroy', [$project, $foreign]),
                ['backlog_item_id' => $foreignItem->id],
            ),
            'items.status' => fn () => $this->patch(
                route('sprints.items.status', [$project, $foreign, $foreignItem]),
                ['status' => BacklogStatus::Done->value],
            ),
        ];

        foreach ($calls as $verb => $call) {
            $this->actingAs($manager);

            $call()->assertNotFound();
        }

        $foreign->refresh();

        $this->assertSame(SprintStatus::Planned, $foreign->status);
        $this->assertSame($foreign->id, $foreignItem->fresh()?->sprint_id);
        $this->assertSame(BacklogStatus::Todo, $foreignItem->fresh()?->status);
        $this->assertDatabaseCount('backlog_status_histories', 0);
    }

    public function test_a_closed_sprint_cannot_be_updated(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->closed()->create(['name' => 'Sprint 1']);

        // Editing a closed sprint is refused on the write as well as the
        // form, so the read-only rule does not hinge on a hidden button.
        $this->actingAs($manager)->patch(
            route('sprints.update', [$project, $sprint]),
            ['name' => 'Renamed', 'starts_at' => '2026-11-02', 'ends_at' => '2026-11-15'],
        )->assertForbidden();

        $this->assertSame('Sprint 1', $sprint->fresh()?->name);
    }

    public function test_members_view_sprints_and_viewers_change_nothing(): void
    {
        $project = Project::factory()->create();
        $member = $this->member($project, ProjectRole::Member);
        $viewer = $this->member($project, ProjectRole::Viewer);
        $sprint = Sprint::factory()->for($project)->create();

        $this->actingAs($member)->get(route('sprints.index', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sprints/index')
                ->has('sprints', 1)
                ->where('can.create', false));

        $this->actingAs($member)->get(route('sprints.show', [$project, $sprint]))
            ->assertOk();

        foreach ([$member, $viewer] as $reader) {
            $this->actingAs($reader)->get(
                route('sprints.create', $project),
            )->assertForbidden();
            $this->actingAs($reader)->post(route('sprints.store', $project), [
                'name' => 'Sneaky',
                'starts_at' => '2026-11-02',
                'ends_at' => '2026-11-15',
            ])->assertForbidden();
            $this->actingAs($reader)->patch(
                route('sprints.start', [$project, $sprint]),
            )->assertForbidden();
            $this->actingAs($reader)->patch(
                route('sprints.close', [$project, $sprint]),
            )->assertForbidden();
        }

        $this->actingAs($viewer)->get(route('sprints.show', [$project, $sprint]))
            ->assertOk();

        $this->assertDatabaseCount('sprints', 1);
        $this->assertSame(SprintStatus::Planned, $sprint->fresh()?->status);
    }

    public function test_archived_project_sprints_are_read_only(): void
    {
        $project = Project::factory()->archived()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->create();

        $this->actingAs($manager)->get(route('sprints.index', $project))->assertOk();
        $this->actingAs($manager)->get(route('sprints.create', $project))
            ->assertForbidden();
        $this->actingAs($manager)->post(route('sprints.store', $project), [
            'name' => 'Late sprint',
            'starts_at' => '2026-11-02',
            'ends_at' => '2026-11-15',
        ])->assertForbidden();
        $this->actingAs($manager)->patch(
            route('sprints.start', [$project, $sprint]),
        )->assertForbidden();
        $this->actingAs($manager)->patch(
            route('sprints.update', [$project, $sprint]),
            ['name' => 'Renamed', 'starts_at' => '2026-11-02', 'ends_at' => '2026-11-15'],
        )->assertForbidden();

        $this->assertDatabaseCount('sprints', 1);
        $this->assertSame(SprintStatus::Planned, $sprint->fresh()?->status);
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $project = Project::factory()->create();
        $sprint = Sprint::factory()->for($project)->create();

        $this->get(route('sprints.index', $project))
            ->assertRedirect(route('login'));
        $this->get(route('sprints.show', [$project, $sprint]))
            ->assertRedirect(route('login'));
    }

    public function test_adding_an_item_of_another_project_is_rejected(): void
    {
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->create();
        $foreign = BacklogItem::factory()->for($otherProject)->create();

        $this->actingAs($manager)->post(
            route('sprints.items.store', [$project, $sprint]),
            ['backlog_item_id' => $foreign->id],
        )->assertSessionHasErrors('backlog_item_id');

        $this->assertNull($foreign->fresh()?->sprint_id);
    }

    public function test_detaching_an_item_of_another_sprint_is_rejected(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->create();
        $otherSprint = Sprint::factory()->for($project)->create();
        $item = BacklogItem::factory()->for($project)->create([
            'sprint_id' => $otherSprint->id,
        ]);

        $this->actingAs($manager)->delete(
            route('sprints.items.destroy', [$project, $sprint]),
            ['backlog_item_id' => $item->id],
        )->assertSessionHasErrors('backlog_item_id');

        $this->assertSame($otherSprint->id, $item->fresh()?->sprint_id);
    }

    public function test_every_route_needs_a_backlog_item_in_the_body(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->create();

        $this->actingAs($manager)->post(
            route('sprints.items.store', [$project, $sprint]),
            [],
        )->assertSessionHasErrors('backlog_item_id');

        $this->actingAs($manager)->delete(
            route('sprints.items.destroy', [$project, $sprint]),
            [],
        )->assertSessionHasErrors('backlog_item_id');
    }

    public function test_an_administrator_runs_sprints_without_a_membership(): void
    {
        $project = Project::factory()->create();
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('sprints.store', $project), [
            'name' => 'Admin sprint',
            'starts_at' => '2026-11-02',
            'ends_at' => '2026-11-15',
        ])->assertRedirect();

        $sprint = Sprint::query()->sole();

        $this->actingAs($admin)->patch(route('sprints.start', [$project, $sprint]))
            ->assertRedirect();
        $this->actingAs($admin)->patch(route('sprints.close', [$project, $sprint]))
            ->assertRedirect();

        $this->assertSame(SprintStatus::Closed, $sprint->fresh()?->status);
    }

    public function test_project_manager_of_another_project_cannot_plan_here(): void
    {
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $outsider = $this->member($otherProject, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->create();

        $this->actingAs($outsider)->get(route('sprints.index', $project))
            ->assertForbidden();
        $this->actingAs($outsider)->get(route('sprints.show', [$project, $sprint]))
            ->assertForbidden();
        $this->actingAs($outsider)->post(route('sprints.store', $project), [
            'name' => 'Sneaky',
            'starts_at' => '2026-11-02',
            'ends_at' => '2026-11-15',
        ])->assertForbidden();

        $this->assertDatabaseCount('sprints', 1);
    }

    public function test_the_show_page_lists_the_planned_item_and_the_pool(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->create();
        $planned = BacklogItem::factory()->for($project)->create(['sprint_id' => $sprint->id]);
        $loose = BacklogItem::factory()->for($project)->create();
        BacklogItem::factory()->for($project)->archived()->create();

        $this->actingAs($manager)->get(route('sprints.show', [$project, $sprint]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sprints/show')
                ->has('board.todo', 1)
                ->where('board.todo.0.id', $planned->id)
                ->has('pool', 1)
                ->where('pool.0.id', $loose->id)
                ->where('can.manage_items', true)
                ->where('can.start', true)
                ->where('can.close', true)
                ->where('can.update', true));
    }

    /**
     * An archived item stays in its sprint: the board is the only place its
     * manager can take it back out, and the deletion of a sprint with work is
     * restricted, so hiding it would strand it. The list must count it too,
     * or the number would promise less work than the link opens.
     */
    public function test_the_sprint_list_counts_every_planned_item(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->create();

        BacklogItem::factory()->for($project)->count(2)->create(['sprint_id' => $sprint->id]);
        // Lowest priority, so it sorts last and its slot is predictable.
        $archived = BacklogItem::factory()->for($project)->archived()->create([
            'sprint_id' => $sprint->id,
            'priority' => BacklogPriority::Low,
        ]);

        $this->actingAs($manager)->get(route('sprints.index', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sprints/index')
                ->has('sprints', 1)
                ->where('sprints.0.items', 3));

        $this->actingAs($manager)->get(route('sprints.show', [$project, $sprint]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('board.todo', 3)
                ->where('board.todo.2.can_move_status', false));

        // It cannot be moved, but it can still leave the sprint.
        $this->actingAs($manager)->delete(
            route('sprints.items.destroy', [$project, $sprint]),
            ['backlog_item_id' => $archived->id],
        )->assertRedirect();

        $this->assertNull($archived->fresh()?->sprint_id);
    }

    public function test_a_dates_only_update_keeps_the_name_and_goal(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->create([
            'name' => 'Sprint 1',
            'goal' => 'Ship it.',
            'starts_at' => '2026-11-02',
            'ends_at' => '2026-11-15',
        ]);

        // A client that sends only the new end date must not have to repeat
        // the fields it does not touch.
        $this->actingAs($manager)->patch(
            route('sprints.update', [$project, $sprint]),
            ['ends_at' => '2026-11-22'],
        )->assertRedirect(route('sprints.show', [$project, $sprint]));

        $sprint->refresh();

        $this->assertSame('Sprint 1', $sprint->name);
        $this->assertSame('Ship it.', $sprint->goal);
        $this->assertSame('2026-11-02', $sprint->starts_at->format('Y-m-d'));
        $this->assertSame('2026-11-22', $sprint->ends_at->format('Y-m-d'));
    }

    public function test_members_and_viewers_cannot_attach_or_detach_items(): void
    {
        $project = Project::factory()->create();
        $member = $this->member($project, ProjectRole::Member);
        $viewer = $this->member($project, ProjectRole::Viewer);
        $sprint = Sprint::factory()->for($project)->create();
        $loose = BacklogItem::factory()->for($project)->create();
        $planned = BacklogItem::factory()->for($project)->create(['sprint_id' => $sprint->id]);

        foreach ([$member, $viewer] as $reader) {
            $this->actingAs($reader)->post(
                route('sprints.items.store', [$project, $sprint]),
                ['backlog_item_id' => $loose->id],
            )->assertForbidden();
            $this->actingAs($reader)->delete(
                route('sprints.items.destroy', [$project, $sprint]),
                ['backlog_item_id' => $planned->id],
            )->assertForbidden();
        }

        $this->assertNull($loose->fresh()?->sprint_id);
        $this->assertSame($sprint->id, $planned->fresh()?->sprint_id);
    }

    public function test_archived_projects_reject_item_planning_and_closure(): void
    {
        $project = Project::factory()->archived()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->active()->create();
        $loose = BacklogItem::factory()->for($project)->create();
        $planned = BacklogItem::factory()->for($project)->create(['sprint_id' => $sprint->id]);

        $this->actingAs($manager)->post(
            route('sprints.items.store', [$project, $sprint]),
            ['backlog_item_id' => $loose->id],
        )->assertForbidden();
        $this->actingAs($manager)->delete(
            route('sprints.items.destroy', [$project, $sprint]),
            ['backlog_item_id' => $planned->id],
        )->assertForbidden();
        $this->actingAs($manager)->patch(
            route('sprints.close', [$project, $sprint]),
        )->assertForbidden();

        $this->assertNull($loose->fresh()?->sprint_id);
        $this->assertSame($sprint->id, $planned->fresh()?->sprint_id);
        $this->assertSame(SprintStatus::Active, $sprint->fresh()?->status);
    }

    public function test_a_stale_start_cannot_reopen_a_closed_sprint(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->create();
        $stale = $sprint->fresh();

        $this->actingAs($manager)->patch(route('sprints.close', [$project, $sprint]))
            ->assertRedirect();

        $this->assertThrows(
            fn () => app(SprintController::class)->start($project, $stale),
            AuthorizationException::class,
        );

        $this->assertSame(SprintStatus::Closed, $sprint->fresh()?->status);
    }

    public function test_deleting_a_sprint_with_items_is_restricted(): void
    {
        $project = Project::factory()->create();
        $sprint = Sprint::factory()->for($project)->closed()->create();
        $item = BacklogItem::factory()->for($project)->create(['sprint_id' => $sprint->id]);

        $this->assertThrows(fn () => $sprint->delete(), QueryException::class);

        $this->assertDatabaseHas('sprints', ['id' => $sprint->id]);
        $this->assertSame($sprint->id, $item->fresh()?->sprint_id);
    }

    public function test_deleting_a_project_with_sprints_is_restricted(): void
    {
        $project = Project::factory()->create();
        $sprint = Sprint::factory()->for($project)->active()->create();

        // The project, sprint and item links all restrict, so a delete never
        // half-cascades through the sprint chain.
        $this->assertThrows(fn () => $project->delete(), QueryException::class);

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertDatabaseHas('sprints', ['id' => $sprint->id]);
    }

    public function test_the_sidebar_shortcut_opens_a_readable_project(): void
    {
        $project = Project::factory()->create(['name' => 'Alpha']);
        $manager = $this->member($project, ProjectRole::Manager);

        $this->actingAs($manager)->get(route('sprints.shortcut'))
            ->assertRedirect(route('sprints.index', $project));

        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('sprints.shortcut'))
            ->assertRedirect(route('projects.index'));
    }

    private function member(Project $project, ProjectRole $role): User
    {
        $user = User::factory()->create();
        ProjectMembership::factory()->for($project)->for($user)->create([
            'project_role' => $role,
        ]);

        return $user;
    }
}
