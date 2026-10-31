<?php

namespace Tests\Feature\Projects;

use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_sees_and_edits_every_project(): void
    {
        $admin = User::factory()->administrator()->create();
        $project = Project::factory()->create(['name' => 'Alpha']);

        $this->actingAs($admin)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/index')
                ->has('projects', 1)
                ->where('projects.0.name', 'Alpha'));

        $this->actingAs($admin)->get(route('projects.show', $project))->assertOk();
        $this->actingAs($admin)->get(route('projects.edit', $project))->assertOk();

        $this->actingAs($admin)->patch(route('projects.update', $project), [
            'name' => 'Alpha renamed',
            'status' => ProjectStatus::Active->value,
        ])->assertRedirect(route('projects.show', $project));

        $this->assertSame('Alpha renamed', $project->fresh()?->name);
    }

    public function test_team_member_with_manager_membership_edits_only_that_project(): void
    {
        $user = $this->userWithRole(UserRole::TeamMember);

        $own = Project::factory()->create(['name' => 'Mine']);
        $own->memberships()->create([
            'user_id' => $user->id,
            'project_role' => ProjectRole::Manager,
        ]);

        $other = Project::factory()->create(['name' => 'Other']);

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1)
                ->where('projects.0.name', 'Mine'));

        $this->actingAs($user)->get(route('projects.show', $own))->assertOk();
        $this->actingAs($user)->get(route('projects.edit', $own))->assertOk();

        $this->actingAs($user)->patch(route('projects.update', $own), [
            'name' => 'Mine renamed',
            'status' => ProjectStatus::Active->value,
        ])->assertRedirect(route('projects.show', $own));

        $this->assertSame('Mine renamed', $own->fresh()?->name);

        $this->actingAs($user)->get(route('projects.show', $other))->assertForbidden();
        $this->actingAs($user)->get(route('projects.edit', $other))->assertForbidden();
        $this->actingAs($user)->patch(route('projects.update', $other), [
            'name' => 'Hijacked',
            'status' => ProjectStatus::Active->value,
        ])->assertForbidden();

        $this->assertSame('Other', $other->fresh()?->name);
    }

    public function test_team_member_without_membership_cannot_view_or_update(): void
    {
        $user = $this->userWithRole(UserRole::TeamMember);
        $project = Project::factory()->create();

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/index')
                ->has('projects', 0));

        $this->actingAs($user)->get(route('projects.show', $project))->assertForbidden();
        $this->actingAs($user)->get(route('projects.edit', $project))->assertForbidden();
        $this->actingAs($user)->patch(route('projects.update', $project), [
            'name' => 'Hijacked',
            'status' => ProjectStatus::Active->value,
        ])->assertForbidden();
        $this->actingAs($user)->patch(route('projects.archive', $project))->assertForbidden();
    }

    public function test_only_permitted_global_roles_may_create_projects(): void
    {
        $teamMember = $this->userWithRole(UserRole::TeamMember);

        $this->actingAs($teamMember)->get(route('projects.create'))->assertForbidden();
        $this->actingAs($teamMember)->post(route('projects.store'), [
            'name' => 'Sneaky',
            'status' => ProjectStatus::Planned->value,
        ])->assertForbidden();

        $this->assertDatabaseCount('projects', 0);

        $projectManager = $this->userWithRole(UserRole::ProjectManager);

        $this->actingAs($projectManager)->get(route('projects.create'))->assertOk();
        $this->actingAs($projectManager)->post(route('projects.store'), [
            'name' => 'Managed',
            'status' => ProjectStatus::Planned->value,
        ])->assertRedirect();

        $project = Project::query()->sole();
        $this->assertSame($projectManager->id, $project->created_by);
        $this->assertDatabaseHas('project_memberships', [
            'project_id' => $project->id,
            'user_id' => $projectManager->id,
            'project_role' => ProjectRole::Manager->value,
        ]);
    }

    public function test_owner_level_roles_see_projects_they_do_not_belong_to(): void
    {
        $project = Project::factory()->create(['name' => 'Shared']);

        foreach ([UserRole::Owner, UserRole::ProductOwner] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('projects.show', $project))->assertOk();
            $this->actingAs($user)->get(route('projects.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->has('projects', 1)
                    ->where('projects.0.name', 'Shared'));
        }
    }

    public function test_guests_cannot_reach_projects(): void
    {
        $project = Project::factory()->create();

        $this->get(route('projects.index'))->assertRedirect(route('login'));
        $this->get(route('projects.show', $project))->assertRedirect(route('login'));
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->roles()->create(['role' => $role]);

        return $user;
    }
}
