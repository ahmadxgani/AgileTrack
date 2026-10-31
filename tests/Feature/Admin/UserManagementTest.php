<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_list_accounts(): void
    {
        $admin = User::factory()->administrator()->create();
        User::factory()->create(['name' => 'Beta Person']);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/index')
            ->has('users.data', 2)
        );
    }

    public function test_administrator_can_create_an_account_with_multiple_roles(): void
    {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Person',
            'email' => 'New.Person@example.com',
            'password' => 'secret-password',
            'roles' => [UserRole::TeamMember->value, UserRole::QaEngineer->value],
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'new.person@example.com')->sole();
        $this->assertNotSame('secret-password', $user->getAuthPassword());
        $this->assertTrue($user->is_active);
        $this->assertEqualsCanonicalizing(
            [UserRole::TeamMember, UserRole::QaEngineer],
            $user->roles->pluck('role')->all(),
        );
    }

    public function test_created_account_can_sign_in_with_the_admin_entered_password(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Person',
            'email' => 'new.person@example.com',
            'password' => 'secret-password',
            'roles' => [UserRole::Customer->value],
        ]);

        $this->post(route('logout'));
        $this->post(route('login.store'), [
            'email' => 'new.person@example.com',
            'password' => 'secret-password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_administrator_can_update_an_account_and_replace_its_roles(): void
    {
        $admin = User::factory()->administrator()->create();
        $member = User::factory()->create();
        $member->roles()->create(['role' => UserRole::TeamMember]);

        $response = $this->actingAs($admin)->patch(route('admin.users.update', $member), [
            'name' => 'Renamed Person',
            'email' => $member->email,
            'roles' => [UserRole::ProductOwner->value],
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.users.edit', $member));

        $member->refresh()->load('roles');
        $this->assertSame('Renamed Person', $member->name);
        $this->assertSame([UserRole::ProductOwner], $member->roles->pluck('role')->all());
    }

    public function test_administrator_can_deactivate_and_reactivate_an_account(): void
    {
        $admin = User::factory()->administrator()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.users.update', $member), [
            'name' => $member->name,
            'email' => $member->email,
            'roles' => [UserRole::TeamMember->value],
            'is_active' => false,
        ])->assertSessionHasNoErrors();

        $this->assertFalse($member->refresh()->is_active);

        $this->actingAs($admin)->patch(route('admin.users.update', $member), [
            'name' => $member->name,
            'email' => $member->email,
            'roles' => [UserRole::TeamMember->value],
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($member->refresh()->is_active);
    }

    public function test_non_administrator_gets_403_on_every_account_route(): void
    {
        $member = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($member)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($member)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($member)->get(route('admin.users.edit', $target))->assertForbidden();
        $this->actingAs($member)->post(route('admin.users.store'), [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'secret-password',
            'roles' => [UserRole::Administrator->value],
        ])->assertForbidden();
        $this->actingAs($member)->patch(route('admin.users.update', $target), [
            'name' => 'Hijacked',
            'email' => $target->email,
            'roles' => [UserRole::Administrator->value],
            'is_active' => true,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
        $this->assertNotSame('Hijacked', $target->refresh()->name);
    }

    public function test_an_inactive_account_loses_its_existing_session(): void
    {
        $inactiveAdmin = User::factory()->administrator()->inactive()->create();

        $this->actingAs($inactiveAdmin)
            ->get(route('admin.users.index'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_inactive_account_cannot_authenticate_with_valid_credentials(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_last_active_administrator_cannot_deactivate_themselves(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => [UserRole::Administrator->value],
            'is_active' => false,
        ])->assertSessionHasErrors('is_active');

        $this->assertTrue($admin->refresh()->is_active);
    }

    public function test_last_active_administrator_cannot_drop_the_administrator_role(): void
    {
        $admin = User::factory()->administrator()->create();
        $other = User::factory()->administrator()->create();

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => [UserRole::TeamMember->value],
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        // Two administrators exist, so dropping one role is allowed.
        $this->assertFalse($admin->refresh()->load('roles')->hasRole(UserRole::Administrator));

        // Now $other is the last active administrator.
        $this->actingAs($other)->patch(route('admin.users.update', $other), [
            'name' => $other->name,
            'email' => $other->email,
            'roles' => [UserRole::TeamMember->value],
            'is_active' => true,
        ])->assertSessionHasErrors('roles');

        $this->assertTrue($other->refresh()->load('roles')->hasRole(UserRole::Administrator));
    }

    public function test_an_inactive_administrator_does_not_count_as_the_remaining_administrator(): void
    {
        $admin = User::factory()->administrator()->create();
        User::factory()->administrator()->inactive()->create();

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => [UserRole::Administrator->value],
            'is_active' => false,
        ])->assertSessionHasErrors('is_active');
    }

    public function test_account_creation_requires_at_least_one_role(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Person',
            'email' => 'new.person@example.com',
            'password' => 'secret-password',
            'roles' => [],
        ])->assertSessionHasErrors('roles');

        $this->assertDatabaseMissing('users', ['email' => 'new.person@example.com']);
    }
}
