<?php

namespace Tests\Feature\Backlog;

use App\Enums\BacklogPriority;
use App\Enums\BacklogStatus;
use App\Enums\ProjectRole;
use App\Enums\UserRole;
use App\Models\BacklogItem;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BacklogAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_manages_the_backlog_of_any_project(): void
    {
        $admin = User::factory()->administrator()->create();
        $project = Project::factory()->create();

        $this->actingAs($admin)->post(route('projects.backlog.store', $project), [
            'title' => 'Admin item',
            'priority' => BacklogPriority::Medium->value,
            'status' => BacklogStatus::Todo->value,
        ])->assertRedirect(route('projects.backlog.index', $project));

        $item = BacklogItem::query()->sole();

        $this->actingAs($admin)->patch(
            route('projects.backlog.status', [$project, $item]),
            ['status' => BacklogStatus::Done->value],
        )->assertRedirect(route('projects.backlog.index', $project));

        $this->actingAs($admin)->patch(
            route('projects.backlog.archive', [$project, $item]),
        )->assertRedirect(route('projects.backlog.index', $project));

        $this->assertSame(BacklogStatus::Done, $item->fresh()?->status);
        $this->assertNotNull($item->fresh()?->archived_at);
    }

    public function test_project_manager_membership_runs_the_backlog(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);

        $this->actingAs($manager)->get(route('projects.backlog.create', $project))->assertOk();

        $this->actingAs($manager)->post(route('projects.backlog.store', $project), [
            'title' => 'Manager item',
            'priority' => BacklogPriority::High->value,
            'status' => BacklogStatus::Todo->value,
        ])->assertRedirect();

        $item = BacklogItem::query()->sole();

        $this->actingAs($manager)->get(
            route('projects.backlog.edit', [$project, $item]),
        )->assertOk();

        $this->actingAs($manager)->patch(
            route('projects.backlog.archive', [$project, $item]),
        )->assertRedirect();
    }

    public function test_assigned_member_may_only_move_the_status(): void
    {
        $project = Project::factory()->create();
        $member = $this->member($project, ProjectRole::Member);
        $item = BacklogItem::factory()->for($project)->create();
        $item->assignees()->attach($member->id);

        $this->actingAs($member)->get(route('projects.backlog.index', $project))->assertOk();
        $this->actingAs($member)->get(route('projects.backlog.create', $project))->assertForbidden();
        $this->actingAs($member)->get(
            route('projects.backlog.edit', [$project, $item]),
        )->assertForbidden();

        $this->actingAs($member)->patch(
            route('projects.backlog.update', [$project, $item]),
            [
                'title' => 'Renamed by member',
                'priority' => BacklogPriority::Urgent->value,
                'status' => BacklogStatus::Done->value,
            ],
        )->assertForbidden();

        $this->actingAs($member)->patch(
            route('projects.backlog.status', [$project, $item]),
            ['status' => BacklogStatus::InProgress->value],
        )->assertRedirect(route('projects.backlog.index', $project));

        $item->refresh();

        $this->assertSame(BacklogStatus::InProgress, $item->status);
        $this->assertSame('Draft work', $item->title);
        $this->assertSame(BacklogPriority::Medium, $item->priority);
    }

    public function test_unassigned_member_cannot_move_the_status(): void
    {
        $project = Project::factory()->create();
        $member = $this->member($project, ProjectRole::Member);
        $item = BacklogItem::factory()->for($project)->create();

        $this->actingAs($member)->patch(
            route('projects.backlog.status', [$project, $item]),
            ['status' => BacklogStatus::Done->value],
        )->assertForbidden();

        $this->assertSame(BacklogStatus::Todo, $item->fresh()?->status);
    }

    public function test_viewer_reads_the_backlog_and_changes_nothing(): void
    {
        $project = Project::factory()->create();
        $viewer = $this->member($project, ProjectRole::Viewer);
        $item = BacklogItem::factory()->for($project)->create();
        $item->assignees()->attach($viewer->id);

        $this->actingAs($viewer)->get(route('projects.backlog.index', $project))
            ->assertOk();
        $this->actingAs($viewer)->get(route('projects.backlog.create', $project))
            ->assertForbidden();
        $this->actingAs($viewer)->get(
            route('projects.backlog.edit', [$project, $item]),
        )->assertForbidden();
        $this->actingAs($viewer)->post(route('projects.backlog.store', $project), [
            'title' => 'Viewer item',
            'priority' => BacklogPriority::Low->value,
            'status' => BacklogStatus::Todo->value,
        ])->assertForbidden();
        $this->actingAs($viewer)->patch(
            route('projects.backlog.status', [$project, $item]),
            ['status' => BacklogStatus::Done->value],
        )->assertForbidden();
        $this->actingAs($viewer)->patch(
            route('projects.backlog.archive', [$project, $item]),
        )->assertForbidden();

        $this->assertSame(BacklogStatus::Todo, $item->fresh()?->status);
        $this->assertNull($item->fresh()?->archived_at);
    }

    public function test_account_without_membership_cannot_touch_the_backlog(): void
    {
        $project = Project::factory()->create();
        $outsider = User::factory()->create();
        $outsider->roles()->create(['role' => UserRole::TeamMember]);
        $item = BacklogItem::factory()->for($project)->create();

        $this->actingAs($outsider)->get(route('projects.backlog.index', $project))
            ->assertForbidden();
        $this->actingAs($outsider)->post(route('projects.backlog.store', $project), [
            'title' => 'Outsider item',
            'priority' => BacklogPriority::Low->value,
            'status' => BacklogStatus::Todo->value,
        ])->assertForbidden();
        $this->actingAs($outsider)->patch(
            route('projects.backlog.status', [$project, $item]),
            ['status' => BacklogStatus::Done->value],
        )->assertForbidden();
        $this->actingAs($outsider)->patch(
            route('projects.backlog.archive', [$project, $item]),
        )->assertForbidden();

        $this->assertDatabaseCount('backlog_items', 1);
    }

    public function test_owner_level_roles_read_the_backlog_but_do_not_run_it(): void
    {
        $project = Project::factory()->create();
        $owner = User::factory()->create();
        $owner->roles()->create(['role' => UserRole::Owner]);
        BacklogItem::factory()->for($project)->create();

        $this->actingAs($owner)->get(route('projects.backlog.index', $project))
            ->assertOk();
        $this->actingAs($owner)->get(route('projects.backlog.create', $project))
            ->assertForbidden();
    }

    public function test_removing_a_member_clears_their_assignments_in_this_project(): void
    {
        $project = Project::factory()->create();
        $manager = $this->member($project, ProjectRole::Manager);
        $teammate = $this->member($project, ProjectRole::Member);
        $item = BacklogItem::factory()->for($project)->create();
        $item->assignees()->attach($teammate->id);

        $otherProject = Project::factory()->create();
        $otherItem = BacklogItem::factory()->for($otherProject)->create();
        $otherItem->assignees()->attach($teammate->id);

        $membership = ProjectMembership::query()
            ->where('project_id', $project->id)
            ->where('user_id', $teammate->id)
            ->sole();

        $this->actingAs($manager)->delete(
            route('projects.members.destroy', [$project, $membership]),
        )->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseMissing('backlog_item_user', [
            'backlog_item_id' => $item->id,
            'user_id' => $teammate->id,
        ]);

        // The same person's assignment in another project is untouched.
        $this->assertDatabaseHas('backlog_item_user', [
            'backlog_item_id' => $otherItem->id,
            'user_id' => $teammate->id,
        ]);

        $this->actingAs($manager)->get(route('projects.backlog.index', $project))
            ->assertInertia(fn (Assert $page) => $page
                ->has('items.0.assignees', 0));
    }

    public function test_sidebar_shortcut_opens_a_readable_backlog(): void
    {
        $project = Project::factory()->create(['name' => 'Alpha']);
        $manager = $this->member($project, ProjectRole::Manager);

        $this->actingAs($manager)->get(route('projects.backlog.shortcut'))
            ->assertRedirect(route('projects.backlog.index', $project));

        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('projects.backlog.shortcut'))
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
