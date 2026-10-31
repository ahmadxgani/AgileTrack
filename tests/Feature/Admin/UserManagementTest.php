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
            'password_confirmation' => 'secret-password',
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
            'password_confirmation' => 'secret-password',
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

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_guests_are_redirected_to_the_login_page_from_admin_routes(): void
    {
        $target = User::factory()->create();

        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
        $this->get(route('admin.users.create'))->assertRedirect(route('login'));
        $this->get(route('admin.users.edit', $target))->assertRedirect(route('login'));
        $this->post(route('admin.users.store'))->assertRedirect(route('login'));
        $this->patch(route('admin.users.update', $target))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_account_creation_rejects_an_unknown_role(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Person',
            'email' => 'new.person@example.com',
            'password' => 'secret-password',
            'roles' => ['chief_robot'],
        ])->assertSessionHasErrors('roles.0');

        $this->assertDatabaseMissing('users', ['email' => 'new.person@example.com']);
    }

    public function test_sole_active_administrator_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->delete(route('profile.destroy'), [
            'password' => 'password',
        ])->assertSessionHasErrors('password');

        $this->assertModelExists($admin->refresh());
        $this->assertAuthenticatedAs($admin);

        // With a second active administrator, deletion is allowed again.
        User::factory()->administrator()->create();
        $adminId = $admin->id;

        $this->actingAs($admin)->delete(route('profile.destroy'), [
            'password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $adminId]);
    }

    public function test_non_administrator_gets_403_even_with_an_invalid_payload(): void
    {
        $member = User::factory()->create();
        $target = User::factory()->create();

        // Authorize before validate: the answer must be 403, not 422.
        $this->actingAs($member)->post(route('admin.users.store'), [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'roles' => ['chief_robot'],
        ])->assertForbidden();

        $this->actingAs($member)->patch(route('admin.users.update', $target), [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'roles' => ['chief_robot'],
            'is_active' => 'maybe',
        ])->assertForbidden();
    }

    public function test_administrator_can_change_an_account_email_without_losing_verification(): void
    {
        $admin = User::factory()->administrator()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.users.update', $member), [
            'name' => $member->name,
            'email' => 'moved@example.com',
            'roles' => [UserRole::TeamMember->value],
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $member->refresh();
        $this->assertSame('moved@example.com', $member->email);
        $this->assertTrue($member->hasVerifiedEmail());

        $this->actingAs($member)->get(route('dashboard'))->assertOk();
    }

    public function test_last_active_administrator_cannot_deactivate_themselves(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => [UserRole::TeamMember->value],
            'is_active' => false,
        ])->assertSessionHasErrors('is_active')->assertSessionDoesntHaveErrors('roles');

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

        // The inactive administrator must not make dropping this role look
        // safe: no active administrator would be left.
        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => [UserRole::TeamMember->value],
            'is_active' => true,
        ])->assertSessionHasErrors('roles');

        $this->assertTrue($admin->refresh()->load('roles')->hasRole(UserRole::Administrator));
    }

    public function test_account_creation_uses_the_configured_password_policy(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Person',
            'email' => 'new.person@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'roles' => [UserRole::TeamMember->value],
        ])->assertSessionHasErrors('password');

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Person',
            'email' => 'new.person@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'different-password',
            'roles' => [UserRole::TeamMember->value],
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'new.person@example.com']);
    }

    public function test_administrator_flag_is_shared_with_the_frontend(): void
    {
        $admin = User::factory()->administrator()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.is_administrator', true));

        $this->actingAs($member)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.is_administrator', false));
    }

    public function test_account_creation_requires_at_least_one_role(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Person',
            'email' => 'new.person@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'roles' => [],
        ])->assertSessionHasErrors('roles');

        $this->assertDatabaseMissing('users', ['email' => 'new.person@example.com']);
    }
}
