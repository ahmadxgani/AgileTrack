<?php

namespace Tests\Feature\Sprints;

use App\Enums\BacklogPriority;
use App\Enums\BacklogStatus;
use App\Enums\ProjectRole;
use App\Models\BacklogItem;
use App\Models\BacklogStatusHistory;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KanbanWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_groups_the_sprint_items_into_columns(): void
    {
        $project = Project::factory()->create(['name' => 'AgileTrack']);
        $manager = $this->member($project, ProjectRole::Manager);
        $teammate = $this->member($project, ProjectRole::Member);
        $sprint = Sprint::factory()->for($project)->active()->create(['name' => 'Sprint 1']);

        $todo = BacklogItem::factory()->for($project)->create([
            'title' => 'Plan the work',
            'sprint_id' => $sprint->id,
            'priority' => BacklogPriority::High,
        ]);
        $todo->assignees()->attach($teammate->id);

        $started = BacklogItem::factory()->for($project)->create([
            'title' => 'Build the board',
            'sprint_id' => $sprint->id,
            'status' => BacklogStatus::InProgress,
        ]);

        $done = BacklogItem::factory()->for($project)->create([
            'title' => 'Read the spec',
            'sprint_id' => $sprint->id,
            'status' => BacklogStatus::Done,
        ]);

        // Work outside the sprint stays in the pool, not on the board.
        $pool = BacklogItem::factory()->for($project)->create(['title' => 'Later work']);

        $this->actingAs($manager)->get(route('sprints.show', [$project, $sprint]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sprints/show')
                ->where('sprint.name', 'Sprint 1')
                ->where('sprint.status', 'active')
                ->has('board.todo', 1)
                ->where('board.todo.0.id', $todo->id)
                ->where('board.todo.0.title', 'Plan the work')
                ->where('board.todo.0.priority_label', 'High')
                ->where('board.todo.0.assignees.0.name', $teammate->name)
                ->where('board.todo.0.can_move_status', true)
                ->has('board.in_progress', 1)
                ->where('board.in_progress.0.id', $started->id)
                ->has('board.done', 1)
                ->where('board.done.0.id', $done->id)
                ->has('pool', 1)
                ->where('pool.0.id', $pool->id)
                ->has('statuses', 3));

        // An assigned member may move the work assigned to them.
        $this->actingAs($teammate)->get(route('sprints.show', [$project, $sprint]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.todo.0.can_move_status', true)
                ->where('board.in_progress.0.can_move_status', false)
                ->where('can.manage_items', false)
                ->where('can.update', false));
    }

    public function test_every_status_change_writes_one_history_row_with_the_actor(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->active()->create();
        $item = BacklogItem::factory()->for($project)->create(['sprint_id' => $sprint->id]);

        $this->actingAs($manager)->patch(
            route('sprints.items.status', [$project, $sprint, $item]),
            ['status' => BacklogStatus::InProgress->value],
        )->assertRedirect(route('sprints.show', [$project, $sprint]));

        $this->actingAs($manager)->patch(
            route('sprints.items.status', [$project, $sprint, $item]),
            ['status' => BacklogStatus::Done->value],
        )->assertRedirect();

        // A backwards move is allowed too.
        $this->actingAs($manager)->patch(
            route('sprints.items.status', [$project, $sprint, $item]),
            ['status' => BacklogStatus::Todo->value],
        )->assertRedirect();

        $this->assertSame(BacklogStatus::Todo, $item->fresh()?->status);
        $this->assertDatabaseCount('backlog_status_histories', 3);

        $histories = BacklogStatusHistory::query()->orderBy('id')->get();

        $this->assertSame(BacklogStatus::Todo, $histories[0]->from_status);
        $this->assertSame(BacklogStatus::InProgress, $histories[0]->to_status);
        $this->assertSame(BacklogStatus::InProgress, $histories[1]->from_status);
        $this->assertSame(BacklogStatus::Done, $histories[1]->to_status);
        $this->assertSame(BacklogStatus::Done, $histories[2]->from_status);
        $this->assertSame(BacklogStatus::Todo, $histories[2]->to_status);
        $this->assertSame([$manager->id], $histories->pluck('changed_by')->unique()->all());
        $this->assertNotNull($histories[0]->created_at);
        $this->assertSame($item->id, $histories[0]->backlog_item_id);
    }

    public function test_repeating_the_current_status_is_rejected_without_a_history_row(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->active()->create();
        $item = BacklogItem::factory()->for($project)->create(['sprint_id' => $sprint->id]);

        $this->actingAs($manager)->patch(
            route('sprints.items.status', [$project, $sprint, $item]),
            ['status' => BacklogStatus::Todo->value],
        )->assertSessionHasErrors('status');

        $this->actingAs($manager)->patch(
            route('projects.backlog.status', [$project, $item]),
            ['status' => BacklogStatus::Todo->value],
        )->assertSessionHasErrors('status');

        $this->assertSame(BacklogStatus::Todo, $item->fresh()?->status);
        $this->assertDatabaseCount('backlog_status_histories', 0);
    }

    public function test_the_backlog_endpoint_writes_the_same_history(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $item = BacklogItem::factory()->for($project)->create();

        $this->actingAs($manager)->patch(
            route('projects.backlog.status', [$project, $item]),
            ['status' => BacklogStatus::Done->value],
        )->assertRedirect(route('projects.backlog.index', $project));

        $this->assertDatabaseHas('backlog_status_histories', [
            'backlog_item_id' => $item->id,
            'from_status' => BacklogStatus::Todo->value,
            'to_status' => BacklogStatus::Done->value,
            'changed_by' => $manager->id,
        ]);
    }

    public function test_the_backlog_form_writes_a_history_row(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $item = BacklogItem::factory()->for($project)->create();

        $this->actingAs($manager)->patch(
            route('projects.backlog.update', [$project, $item]),
            [
                'title' => 'Renamed',
                'priority' => BacklogPriority::Low->value,
                'status' => BacklogStatus::InProgress->value,
            ],
        )->assertRedirect();

        $this->assertDatabaseCount('backlog_status_histories', 1);

        // Saving the same status again is not a move, so nothing is logged.
        $this->actingAs($manager)->patch(
            route('projects.backlog.update', [$project, $item]),
            [
                'title' => 'Renamed again',
                'priority' => BacklogPriority::Low->value,
                'status' => BacklogStatus::InProgress->value,
            ],
        )->assertRedirect();

        $this->assertDatabaseCount('backlog_status_histories', 1);
    }

    public function test_assigned_member_moves_only_their_own_work_on_the_board(): void
    {
        $project = Project::factory()->create();
        $member = $this->member($project, ProjectRole::Member);
        $sprint = Sprint::factory()->for($project)->active()->create();
        $mine = BacklogItem::factory()->for($project)->create(['sprint_id' => $sprint->id]);
        $theirs = BacklogItem::factory()->for($project)->create(['sprint_id' => $sprint->id]);
        $mine->assignees()->attach($member->id);

        $this->actingAs($member)->patch(
            route('sprints.items.status', [$project, $sprint, $mine]),
            ['status' => BacklogStatus::InProgress->value],
        )->assertRedirect();

        $this->assertSame(BacklogStatus::InProgress, $mine->fresh()?->status);
        $this->assertDatabaseCount('backlog_status_histories', 1);

        $this->actingAs($member)->patch(
            route('sprints.items.status', [$project, $sprint, $theirs]),
            ['status' => BacklogStatus::InProgress->value],
        )->assertForbidden();

        $this->assertSame(BacklogStatus::Todo, $theirs->fresh()?->status);
        $this->assertDatabaseCount('backlog_status_histories', 1);
    }

    public function test_viewer_cannot_move_work_even_when_assigned(): void
    {
        $project = Project::factory()->create();
        $viewer = $this->member($project, ProjectRole::Viewer);
        $sprint = Sprint::factory()->for($project)->active()->create();
        $item = BacklogItem::factory()->for($project)->create(['sprint_id' => $sprint->id]);
        $item->assignees()->attach($viewer->id);

        $this->actingAs($viewer)->patch(
            route('sprints.items.status', [$project, $sprint, $item]),
            ['status' => BacklogStatus::Done->value],
        )->assertForbidden();

        $this->assertSame(BacklogStatus::Todo, $item->fresh()?->status);
        $this->assertDatabaseCount('backlog_status_histories', 0);
    }

    public function test_item_of_another_project_is_not_reachable_through_the_board(): void
    {
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->active()->create();
        $foreignSprint = Sprint::factory()->for($otherProject)->active()->create();
        $foreign = BacklogItem::factory()->for($otherProject)->create([
            'sprint_id' => $foreignSprint->id,
        ]);

        $this->actingAs($manager)->patch(
            route('sprints.items.status', [$project, $sprint, $foreign]),
            ['status' => BacklogStatus::Done->value],
        )->assertNotFound();

        $this->assertSame(BacklogStatus::Todo, $foreign->fresh()?->status);
    }

    public function test_archived_project_refuses_board_moves(): void
    {
        $project = Project::factory()->archived()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->active()->create();
        $item = BacklogItem::factory()->for($project)->create(['sprint_id' => $sprint->id]);

        $this->actingAs($manager)->patch(
            route('sprints.items.status', [$project, $sprint, $item]),
            ['status' => BacklogStatus::Done->value],
        )->assertForbidden();

        $this->assertDatabaseCount('backlog_status_histories', 0);
    }

    public function test_history_survives_the_deletion_of_the_actor(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->active()->create();
        $item = BacklogItem::factory()->for($project)->create(['sprint_id' => $sprint->id]);

        $this->actingAs($manager)->patch(
            route('sprints.items.status', [$project, $sprint, $item]),
            ['status' => BacklogStatus::InProgress->value],
        )->assertRedirect();

        $manager->delete();

        $this->assertDatabaseHas('backlog_status_histories', [
            'backlog_item_id' => $item->id,
            'changed_by' => null,
        ]);
    }

    /**
     * Every move between the three columns is allowed, so the board never
     * strands work in one state.
     */
    public function test_all_six_transitions_are_allowed(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->active()->create();
        $item = BacklogItem::factory()->for($project)->create(['sprint_id' => $sprint->id]);

        $moves = [
            [BacklogStatus::Todo, BacklogStatus::InProgress],
            [BacklogStatus::InProgress, BacklogStatus::Done],
            [BacklogStatus::Done, BacklogStatus::Todo],
            [BacklogStatus::Todo, BacklogStatus::Done],
            [BacklogStatus::Done, BacklogStatus::InProgress],
            [BacklogStatus::InProgress, BacklogStatus::Todo],
        ];

        foreach ($moves as $index => [$from, $to]) {
            // Set up the starting state without recording a workflow move.
            BacklogItem::query()
                ->whereKey($item->getKey())
                ->toBase()
                ->update(['status' => $from->value]);

            $this->actingAs($manager)->patch(
                route('sprints.items.status', [$project, $sprint, $item]),
                ['status' => $to->value],
            )->assertRedirect()
                ->assertSessionHasNoErrors();

            $this->assertSame($to, $item->fresh()?->status, "move $index");
        }

        $this->assertDatabaseCount('backlog_status_histories', 6);
    }

    public function test_a_json_request_gets_a_422_for_a_rejected_move(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->closed()->create();
        $item = BacklogItem::factory()->for($project)->create(['sprint_id' => $sprint->id]);

        $this->actingAs($manager)->patchJson(
            route('sprints.items.status', [$project, $sprint, $item]),
            ['status' => BacklogStatus::Done->value],
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        // The same holds for a no-op.
        $open = Sprint::factory()->for($project)->active()->create();
        $loose = BacklogItem::factory()->for($project)->create(['sprint_id' => $open->id]);

        $this->actingAs($manager)->patchJson(
            route('sprints.items.status', [$project, $open, $loose]),
            ['status' => BacklogStatus::Todo->value],
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseCount('backlog_status_histories', 0);
    }

    public function test_an_administrator_moves_any_item_without_a_membership(): void
    {
        $project = Project::factory()->create();
        $admin = User::factory()->administrator()->create();
        $sprint = Sprint::factory()->for($project)->active()->create();
        $item = BacklogItem::factory()->for($project)->create(['sprint_id' => $sprint->id]);

        $this->actingAs($admin)->patch(
            route('sprints.items.status', [$project, $sprint, $item]),
            ['status' => BacklogStatus::Done->value],
        )->assertRedirect();

        $this->assertSame(BacklogStatus::Done, $item->fresh()?->status);
        $this->assertDatabaseHas('backlog_status_histories', ['changed_by' => $admin->id]);
    }

    public function test_a_closed_sprint_hides_the_status_controls(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->closed()->create();
        BacklogItem::factory()->for($project)->create(['sprint_id' => $sprint->id]);

        $this->actingAs($manager)->get(route('sprints.show', [$project, $sprint]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.todo.0.can_move_status', false)
                ->where('can.manage_items', false)
                ->where('can.update', false)
                ->where('can.start', false)
                ->where('can.close', false));
    }

    public function test_the_backlog_form_rolls_back_when_the_history_write_fails(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $item = BacklogItem::factory()->for($project)->create();

        // Dropping the log table makes the second write of the transaction
        // fail, so the item update must not be kept.
        Schema::drop('backlog_status_histories');

        $this->actingAs($manager)->patch(
            route('projects.backlog.update', [$project, $item]),
            [
                'title' => 'Renamed',
                'priority' => BacklogPriority::Low->value,
                'status' => BacklogStatus::Done->value,
            ],
        )->assertInternalServerError();

        $item->refresh();

        $this->assertSame(BacklogStatus::Todo, $item->status);
        $this->assertSame('Draft work', $item->title);
    }

    public function test_the_backlog_form_cannot_move_work_of_a_closed_sprint(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $sprint = Sprint::factory()->for($project)->closed()->create();
        $item = BacklogItem::factory()->for($project)->create(['sprint_id' => $sprint->id]);

        // The form edits the status through the same path as the board, so a
        // closed sprint freezes it there too instead of only on the board.
        $this->actingAs($manager)->patch(
            route('projects.backlog.update', [$project, $item]),
            [
                'title' => 'Renamed',
                'priority' => BacklogPriority::Low->value,
                'status' => BacklogStatus::Done->value,
            ],
        )->assertSessionHasErrors('status');

        $item->refresh();

        $this->assertSame(BacklogStatus::Todo, $item->status);
        $this->assertSame('Draft work', $item->title);
        $this->assertDatabaseCount('backlog_status_histories', 0);
    }

    public function test_an_archived_item_keeps_its_status(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $item = BacklogItem::factory()->for($project)->archived()->create();

        $this->actingAs($manager)->patch(
            route('projects.backlog.status', [$project, $item]),
            ['status' => BacklogStatus::Done->value],
        )->assertForbidden();

        $this->assertSame(BacklogStatus::Todo, $item->fresh()?->status);
        $this->assertDatabaseCount('backlog_status_histories', 0);
    }

    public function test_a_stale_assignment_cannot_authorize_a_status_change(): void
    {
        $project = Project::factory()->create();
        $member = $this->member($project, ProjectRole::Member);
        $sprint = Sprint::factory()->for($project)->active()->create();
        $item = BacklogItem::factory()->for($project)->create(['sprint_id' => $sprint->id]);
        $item->assignees()->attach($member);
        $item->load('assignees', 'project.memberships');

        $item->assignees()->detach($member);

        $this->assertThrows(
            fn () => $item->transitionStatus(BacklogStatus::Done, $member),
            AuthorizationException::class,
        );

        $this->assertSame(BacklogStatus::Todo, $item->fresh()?->status);
        $this->assertDatabaseCount('backlog_status_histories', 0);
    }

    public function test_a_stale_project_cannot_authorize_a_status_change_after_archival(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $item = BacklogItem::factory()->for($project)->create();
        $item->load('project.memberships');
        $project->forceFill(['archived_at' => now()])->save();

        $this->assertThrows(
            fn () => $item->transitionStatus(BacklogStatus::Done, $manager),
            AuthorizationException::class,
        );

        $this->assertSame(BacklogStatus::Todo, $item->fresh()?->status);
        $this->assertDatabaseCount('backlog_status_histories', 0);
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
