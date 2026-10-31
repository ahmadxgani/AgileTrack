<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_requires_a_name_and_a_sane_date_range(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('projects.store'), [
            'name' => '',
            'status' => ProjectStatus::Planned->value,
        ])->assertSessionHasErrors('name');

        $this->actingAs($admin)->post(route('projects.store'), [
            'name' => str_repeat('a', 256),
            'status' => ProjectStatus::Planned->value,
        ])->assertSessionHasErrors('name');

        $this->actingAs($admin)->post(route('projects.store'), [
            'name' => 'Backwards',
            'starts_at' => '2026-05-10',
            'ends_at' => '2026-05-01',
            'status' => ProjectStatus::Planned->value,
        ])->assertSessionHasErrors('ends_at');

        $this->actingAs($admin)->post(route('projects.store'), [
            'name' => 'Unknown status',
            'status' => 'archived',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_creator_can_store_a_project_and_becomes_its_manager(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('projects.store'), [
            'name' => 'AgileTrack',
            'description' => 'Student project tracker',
            'starts_at' => '2026-11-01',
            'ends_at' => '2026-12-01',
            'status' => ProjectStatus::Planned->value,
        ])->assertRedirect();

        $project = Project::query()->sole();

        $this->assertSame($admin->id, $project->created_by);
        $this->assertSame(ProjectStatus::Planned, $project->status);
        $this->assertDatabaseHas('project_memberships', [
            'project_id' => $project->id,
            'user_id' => $admin->id,
            'project_role' => ProjectRole::Manager->value,
        ]);
    }

    public function test_archived_project_is_visible_but_read_only(): void
    {
        $admin = User::factory()->administrator()->create();
        $project = Project::factory()->archived()->create(['name' => 'Frozen']);

        $this->actingAs($admin)->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1)
                ->where('projects.0.archived_at', fn ($value) => is_string($value)));

        $this->actingAs($admin)->get(route('projects.show', $project))->assertOk();

        // A JSON request gets the 422; a browser form gets the same rejection
        // as a validation error on the field.
        $this->actingAs($admin)->patchJson(route('projects.update', $project), [
            'name' => 'Thawed',
            'status' => ProjectStatus::Active->value,
        ])->assertStatus(422);

        $this->actingAs($admin)->patch(route('projects.update', $project), [
            'name' => 'Thawed',
            'status' => ProjectStatus::Active->value,
        ])->assertSessionHasErrors('name');

        $this->actingAs($admin)->patch(route('projects.archive', $project))->assertSessionHasErrors('name');

        $this->assertSame('Frozen', $project->fresh()?->name);
    }

    public function test_archived_project_members_are_read_only(): void
    {
        $admin = User::factory()->administrator()->create();
        $project = Project::factory()->archived()->create();
        $membership = ProjectMembership::factory()->for($project)->create();
        $newcomer = User::factory()->create(['email' => 'newcomer@example.com']);

        $this->actingAs($admin)->post(route('projects.members.store', $project), [
            'email' => $newcomer->email,
            'project_role' => ProjectRole::Member->value,
        ])->assertSessionHasErrors('project_role');

        $this->actingAs($admin)->patch(
            route('projects.members.update', [$project, $membership]),
            ['project_role' => ProjectRole::Viewer->value],
        )->assertSessionHasErrors('project_role');

        $this->actingAs($admin)->delete(
            route('projects.members.destroy', [$project, $membership]),
        )->assertSessionHasErrors('project_role');

        $this->assertDatabaseMissing('project_memberships', ['user_id' => $newcomer->id]);
        $this->assertDatabaseHas('project_memberships', [
            'id' => $membership->id,
            'project_role' => ProjectRole::Manager->value,
        ]);
    }

    public function test_membership_rows_of_another_project_are_not_reachable(): void
    {
        $admin = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $foreign = ProjectMembership::factory()->for($otherProject)->create();

        $this->actingAs($admin)->patch(
            route('projects.members.update', [$project, $foreign]),
            ['project_role' => ProjectRole::Viewer->value],
        )->assertNotFound();

        $this->actingAs($admin)->delete(
            route('projects.members.destroy', [$project, $foreign]),
        )->assertNotFound();

        $this->assertDatabaseHas('project_memberships', [
            'id' => $foreign->id,
            'project_role' => ProjectRole::Manager->value,
        ]);
    }

    public function test_update_may_send_one_date_and_still_be_checked_against_the_stored_other(): void
    {
        $admin = User::factory()->administrator()->create();
        $project = Project::factory()->create([
            'starts_at' => '2026-05-10',
            'ends_at' => '2026-05-20',
        ]);

        $payload = ['name' => $project->name, 'status' => ProjectStatus::Active->value];

        // Only the end date, before the stored start date.
        $this->actingAs($admin)->patch(route('projects.update', $project), [
            ...$payload,
            'ends_at' => '2026-05-01',
        ])->assertSessionHasErrors('ends_at');

        // Only the start date, after the stored end date.
        $this->actingAs($admin)->patch(route('projects.update', $project), [
            ...$payload,
            'starts_at' => '2026-05-25',
        ])->assertSessionHasErrors('ends_at');

        // Non-ISO input is parsed as a date, not compared as a string: this
        // one sorts after the stored start lexically but is a month before.
        $this->actingAs($admin)->patch(route('projects.update', $project), [
            ...$payload,
            'ends_at' => 'May 1 2026',
        ])->assertSessionHasErrors('ends_at');

        $this->assertSame('2026-05-10', $project->fresh()?->starts_at?->format('Y-m-d'));
        $this->assertSame('2026-05-20', $project->fresh()?->ends_at?->format('Y-m-d'));

        // Each direction still accepts a valid single date.
        $this->actingAs($admin)->patch(route('projects.update', $project), [
            ...$payload,
            'ends_at' => '2026-06-01',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->patch(route('projects.update', $project), [
            ...$payload,
            'starts_at' => '2026-05-01',
        ])->assertSessionHasNoErrors();

        // A non-ISO start before the stored end is accepted too.
        $this->actingAs($admin)->patch(route('projects.update', $project), [
            ...$payload,
            'starts_at' => 'May 2 2026',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->patch(route('projects.update', $project), [
            ...$payload,
            'starts_at' => '2026-05-10',
            'ends_at' => null,
        ])->assertSessionHasNoErrors();

        $this->assertNull($project->fresh()?->ends_at);
    }

    public function test_project_manager_membership_can_add_change_and_remove_members(): void
    {
        $admin = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        $target = User::factory()->create(['email' => 'target@example.com']);

        $this->actingAs($admin)->post(route('projects.members.store', $project), [
            'email' => 'TARGET@example.com',
            'project_role' => ProjectRole::Member->value,
        ])->assertRedirect(route('projects.show', $project));

        $membership = ProjectMembership::query()
            ->where('project_id', $project->id)
            ->where('user_id', $target->id)
            ->sole();

        $this->assertSame(ProjectRole::Member, $membership->project_role);

        $this->actingAs($admin)->patch(
            route('projects.members.update', [$project, $membership]),
            ['project_role' => ProjectRole::Viewer->value],
        )->assertRedirect(route('projects.show', $project));

        $this->assertSame(ProjectRole::Viewer, $membership->fresh()?->project_role);

        $this->actingAs($admin)->delete(
            route('projects.members.destroy', [$project, $membership]),
        )->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseMissing('project_memberships', ['id' => $membership->id]);
    }

    public function test_member_management_rejects_unknown_or_inactive_accounts_and_duplicates(): void
    {
        $admin = User::factory()->administrator()->create();
        $project = Project::factory()->create();
        $inactive = User::factory()->inactive()->create(['email' => 'gone@example.com']);

        $this->actingAs($admin)->post(route('projects.members.store', $project), [
            'email' => 'nobody@example.com',
            'project_role' => ProjectRole::Member->value,
        ])->assertSessionHasErrors('email');

        $this->actingAs($admin)->post(route('projects.members.store', $project), [
            'email' => $inactive->email,
            'project_role' => ProjectRole::Member->value,
        ])->assertSessionHasErrors('email');

        $existing = ProjectMembership::factory()->for($project)->create();

        $this->actingAs($admin)->post(route('projects.members.store', $project), [
            'email' => $existing->user->email,
            'project_role' => ProjectRole::Member->value,
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('project_memberships', 1);
    }

    public function test_project_member_without_manager_role_cannot_manage_members(): void
    {
        $project = Project::factory()->create();
        $member = User::factory()->create();
        $member->roles()->create(['role' => UserRole::TeamMember]);
        ProjectMembership::factory()->for($project)->member()->for($member)->create();

        $target = User::factory()->create(['email' => 'target@example.com']);

        $this->actingAs($member)->get(route('projects.show', $project))->assertOk();

        $this->actingAs($member)->post(route('projects.members.store', $project), [
            'email' => $target->email,
            'project_role' => ProjectRole::Member->value,
        ])->assertForbidden();

        $this->assertDatabaseMissing('project_memberships', ['user_id' => $target->id]);
    }

    public function test_member_and_viewer_memberships_cannot_change_or_remove_members(): void
    {
        $project = Project::factory()->create();
        $target = ProjectMembership::factory()->for($project)->member()->create();

        foreach ([ProjectRole::Member, ProjectRole::Viewer] as $role) {
            $actor = User::factory()->create();
            ProjectMembership::factory()->for($project)->for($actor)->create([
                'project_role' => $role,
            ]);

            $this->actingAs($actor)->patch(
                route('projects.members.update', [$project, $target]),
                ['project_role' => ProjectRole::Viewer->value],
            )->assertForbidden();

            $this->actingAs($actor)->delete(
                route('projects.members.destroy', [$project, $target]),
            )->assertForbidden();

            $this->assertSame(ProjectRole::Member, $target->fresh()?->project_role);
        }
    }

    public function test_last_manager_cannot_be_demoted_or_removed(): void
    {
        $project = Project::factory()->create();
        $manager = User::factory()->create();
        $manager->roles()->create(['role' => UserRole::TeamMember]);
        $own = ProjectMembership::factory()->for($project)->for($manager)->create();

        // A member cannot demote themselves into leaving the project unmanaged.
        $this->actingAs($manager)->patch(
            route('projects.members.update', [$project, $own]),
            ['project_role' => ProjectRole::Member->value],
        )->assertSessionHasErrors('project_role');

        $this->assertSame(ProjectRole::Manager, $own->fresh()?->project_role);

        $this->actingAs($manager)->delete(
            route('projects.members.destroy', [$project, $own]),
        )->assertSessionHasErrors('project_role');

        $this->assertDatabaseHas('project_memberships', ['id' => $own->id]);

        // With a second manager the demotion is fine.
        $second = ProjectMembership::factory()->for($project)->create();

        $this->actingAs($manager)->patch(
            route('projects.members.update', [$project, $own]),
            ['project_role' => ProjectRole::Member->value],
        )->assertRedirect(route('projects.show', $project));

        $this->assertSame(ProjectRole::Member, $own->fresh()?->project_role);

        // The remaining manager can now be neither demoted nor removed.
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)->delete(
            route('projects.members.destroy', [$project, $second]),
        )->assertSessionHasErrors('project_role');

        $this->assertDatabaseHas('project_memberships', ['id' => $second->id]);
    }

    public function test_archived_project_cannot_be_edited_through_the_form(): void
    {
        $admin = User::factory()->administrator()->create();
        $project = Project::factory()->archived()->create();

        $this->actingAs($admin)->get(route('projects.edit', $project))
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)->get(route('projects.show', $project))->assertOk();
    }

    public function test_show_page_lists_members_and_flags_the_viewer_can_manage_them(): void
    {
        $project = Project::factory()->create();
        $manager = User::factory()->create();
        ProjectMembership::factory()->for($project)->for($manager)->create();

        $this->actingAs($manager)->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/show')
                ->has('project.members', 1)
                ->where('project.members.0.id', $manager->id)
                ->where('can.manage_members', true)
                ->where('can.update', true));

        $viewer = User::factory()->create();
        ProjectMembership::factory()->for($project)->viewer()->for($viewer)->create();

        $this->actingAs($viewer)->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.manage_members', false)
                ->where('can.update', false));
    }

    public function test_project_update_rejects_a_member_who_is_not_a_manager(): void
    {
        $project = Project::factory()->create();
        $member = User::factory()->create();
        ProjectMembership::factory()->for($project)->member()->for($member)->create();

        $this->actingAs($member)->get(route('projects.edit', $project))->assertForbidden();

        $this->actingAs($member)->patch(route('projects.update', $project), [
            'name' => 'Nope',
            'status' => ProjectStatus::Active->value,
        ])->assertForbidden();
    }
}
