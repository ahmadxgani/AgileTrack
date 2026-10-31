<?php

namespace Tests\Feature\Backlog;

use App\Enums\BacklogPriority;
use App\Enums\BacklogStatus;
use App\Enums\ProjectRole;
use App\Models\BacklogItem;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BacklogWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_manager_stores_an_item_with_assignees(): void
    {
        $project = Project::factory()->create(['name' => 'AgileTrack']);
        $manager = $this->member($project, ProjectRole::Manager);
        $teammate = $this->member($project, ProjectRole::Member);

        $this->actingAs($manager)->post(route('projects.backlog.store', $project), [
            'title' => 'Draft the backlog',
            'description' => 'List the first slice.',
            'priority' => BacklogPriority::High->value,
            'status' => BacklogStatus::Todo->value,
            'assignees' => [$teammate->id],
        ])->assertRedirect(route('projects.backlog.index', $project));

        $item = BacklogItem::query()->sole();

        $this->assertSame($project->id, $item->project_id);
        $this->assertSame('Draft the backlog', $item->title);
        $this->assertSame(BacklogPriority::High, $item->priority);
        $this->assertSame(BacklogStatus::Todo, $item->status);
        $this->assertSame($manager->id, $item->created_by);
        $this->assertDatabaseHas('backlog_item_user', [
            'backlog_item_id' => $item->id,
            'user_id' => $teammate->id,
        ]);
    }

    public function test_item_requires_a_title_and_known_priority_and_status(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);

        $payload = [
            'title' => 'Something',
            'priority' => BacklogPriority::Medium->value,
            'status' => BacklogStatus::Todo->value,
        ];

        $this->actingAs($manager)->post(route('projects.backlog.store', $project), [
            ...$payload,
            'title' => '',
        ])->assertSessionHasErrors('title');

        $this->actingAs($manager)->post(route('projects.backlog.store', $project), [
            ...$payload,
            'title' => str_repeat('a', 256),
        ])->assertSessionHasErrors('title');

        $this->actingAs($manager)->post(route('projects.backlog.store', $project), [
            ...$payload,
            'priority' => 'blocker',
        ])->assertSessionHasErrors('priority');

        $this->actingAs($manager)->post(route('projects.backlog.store', $project), [
            ...$payload,
            'status' => 'blocked',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseCount('backlog_items', 0);
    }

    public function test_assignees_must_be_members_of_the_project(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $outsider = User::factory()->create();

        $this->actingAs($manager)->post(route('projects.backlog.store', $project), [
            'title' => 'Unassigned',
            'priority' => BacklogPriority::Low->value,
            'status' => BacklogStatus::Todo->value,
            'assignees' => [$outsider->id],
        ])->assertSessionHasErrors('assignees.0');

        $this->assertDatabaseCount('backlog_items', 0);

        // The same guard applies when the assignee list of an existing item
        // is replaced.
        $item = BacklogItem::factory()->for($project)->create();

        $this->actingAs($manager)->patch(
            route('projects.backlog.update', [$project, $item]),
            [
                'title' => $item->title,
                'priority' => $item->priority->value,
                'status' => $item->status->value,
                'assignees' => [$outsider->id],
            ],
        )->assertSessionHasErrors('assignees.0');

        $this->assertDatabaseCount('backlog_item_user', 0);
    }

    public function test_manager_updates_fields_and_replaces_assignees(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $first = $this->member($project, ProjectRole::Member);
        $second = $this->member($project, ProjectRole::Member);
        $item = BacklogItem::factory()->for($project)->create();
        $item->assignees()->attach($first->id);

        $this->actingAs($manager)->patch(
            route('projects.backlog.update', [$project, $item]),
            [
                'title' => 'Renamed',
                'description' => 'New detail.',
                'priority' => BacklogPriority::Urgent->value,
                'status' => BacklogStatus::InProgress->value,
                'assignees' => [$second->id],
            ],
        )->assertRedirect(route('projects.backlog.index', $project));

        $item->refresh();

        $this->assertSame('Renamed', $item->title);
        $this->assertSame('New detail.', $item->description);
        $this->assertSame(BacklogPriority::Urgent, $item->priority);
        $this->assertSame(BacklogStatus::InProgress, $item->status);
        $this->assertDatabaseMissing('backlog_item_user', ['user_id' => $first->id]);
        $this->assertDatabaseHas('backlog_item_user', ['user_id' => $second->id]);
    }

    public function test_archive_keeps_the_item_but_makes_it_read_only(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $item = BacklogItem::factory()->for($project)->create();

        $this->actingAs($manager)->patch(
            route('projects.backlog.archive', [$project, $item]),
        )->assertRedirect(route('projects.backlog.index', $project));

        $this->assertNotNull($item->fresh()?->archived_at);

        $this->actingAs($manager)->get(
            route('projects.backlog.edit', [$project, $item]),
        )->assertForbidden();

        $this->actingAs($manager)->patch(
            route('projects.backlog.update', [$project, $item]),
            [
                'title' => 'Thawed',
                'priority' => BacklogPriority::Low->value,
                'status' => BacklogStatus::Todo->value,
            ],
        )->assertForbidden();

        $this->actingAs($manager)->patch(
            route('projects.backlog.status', [$project, $item]),
            ['status' => BacklogStatus::Done->value],
        )->assertForbidden();

        // Still listed, so history stays visible.
        $this->actingAs($manager)->get(route('projects.backlog.index', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('backlog/index')
                ->has('items', 1)
                ->where('items.0.archived_at', fn ($value) => is_string($value)));

        $this->assertSame('Draft work', $item->fresh()?->title);
    }

    public function test_archived_project_accepts_no_backlog_change(): void
    {
        $manager = User::factory()->create();
        $project = Project::factory()->archived()->create();
        ProjectMembership::factory()->for($project)->for($manager)->create();
        $item = BacklogItem::factory()->for($project)->create();

        $this->actingAs($manager)->get(route('projects.backlog.create', $project))
            ->assertForbidden();

        $this->actingAs($manager)->post(route('projects.backlog.store', $project), [
            'title' => 'Sneaky',
            'priority' => BacklogPriority::Low->value,
            'status' => BacklogStatus::Todo->value,
        ])->assertForbidden();

        $this->actingAs($manager)->patch(
            route('projects.backlog.archive', [$project, $item]),
        )->assertForbidden();

        $this->actingAs($manager)->patch(
            route('projects.backlog.status', [$project, $item]),
            ['status' => BacklogStatus::Done->value],
        )->assertForbidden();

        $this->assertDatabaseCount('backlog_items', 1);
        $this->assertNull($item->fresh()?->archived_at);
        $this->assertSame(BacklogStatus::Todo, $item->fresh()?->status);
    }

    public function test_item_of_another_project_is_not_reachable(): void
    {
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $foreign = BacklogItem::factory()->for($otherProject)->create();

        $this->actingAs($manager)->get(
            route('projects.backlog.edit', [$project, $foreign]),
        )->assertNotFound();

        $this->actingAs($manager)->patch(
            route('projects.backlog.update', [$project, $foreign]),
            [
                'title' => 'Hijacked',
                'priority' => BacklogPriority::Low->value,
                'status' => BacklogStatus::Todo->value,
            ],
        )->assertNotFound();

        $this->actingAs($manager)->patch(
            route('projects.backlog.status', [$project, $foreign]),
            ['status' => BacklogStatus::Done->value],
        )->assertNotFound();

        $this->actingAs($manager)->patch(
            route('projects.backlog.archive', [$project, $foreign]),
        )->assertNotFound();

        $this->assertSame('Draft work', $foreign->fresh()?->title);
        $this->assertNull($foreign->fresh()?->archived_at);
    }

    public function test_index_lists_items_by_priority_and_exposes_permissions(): void
    {
        $project = Project::factory()->create(['name' => 'AgileTrack']);
        $manager = $this->member($project, ProjectRole::Manager);
        $teammate = $this->member($project, ProjectRole::Member);

        BacklogItem::factory()->for($project)->create([
            'title' => 'Low work',
            'priority' => BacklogPriority::Low,
        ]);
        $urgent = BacklogItem::factory()->for($project)->create([
            'title' => 'Urgent work',
            'priority' => BacklogPriority::Urgent,
        ]);
        $urgent->assignees()->attach($teammate->id);

        $this->actingAs($manager)->get(route('projects.backlog.index', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('backlog/index')
                ->where('project.name', 'AgileTrack')
                ->has('items', 2)
                ->where('items.0.title', 'Urgent work')
                ->where('items.0.priority_label', 'Urgent')
                ->where('items.0.assignees.0.name', $teammate->name)
                ->where('items.0.can.update', true)
                ->where('items.0.can.update_status', true)
                ->where('items.0.can.archive', true)
                ->where('items.1.title', 'Low work')
                ->where('can.create', true));

        $this->actingAs($teammate)->get(route('projects.backlog.index', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.0.title', 'Urgent work')
                ->where('items.0.can.update', false)
                ->where('items.0.can.update_status', true)
                ->where('items.0.can.archive', false)
                ->where('items.1.can.update_status', false)
                ->where('can.create', false));
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $project = Project::factory()->create();

        $this->get(route('projects.backlog.index', $project))
            ->assertRedirect(route('login'));
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
